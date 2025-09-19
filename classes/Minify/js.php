<?php
namespace Minify;
/**
 * Simple JavaScript Minifier in pure PHP 7.4+
 * -------------------------------------------
 * - Removes line (//) and block (/* *\/ ) comments (preserves /*! ... *\/ by default)
 * - Collapses unnecessary whitespace while keeping semantics safe(ish)
 * - Preserves strings (' " `), regex literals /.../ and template literals with ${...}
 * - No external dependencies
 *
 * Notes & Limits:
 * - This is a conservative minifier. It avoids risky transformations (e.g., ASI/semicolon gymnastics).
 * - Regex detection uses common heuristics and should be fine for most real-world scripts.
 * - If you hit an edge case, try `$options['aggressive'] = false` (default) or set `$options['preserve_important'] = true` (default).
 */

class js
{
    /**
     * Minify JavaScript code.
     *
     * @param string $js      The JavaScript source.
     * @param array  $options Options:
     *   - preserve_important (bool): keep /*! ... *\/ comments. Default true
     *   - aggressive (bool): if true, remove more whitespace. Default false (safer)
     * @return string
     */
    public static function emit(string $js, array $options = []): string
    {
        $preserveImportant = $options['preserve_important'] ?? true;
        $aggressive        = $options['aggressive'] ?? false;

        $len = strlen($js);
        if ($len === 0) {
            return '';
        }

        $out  = '';
        $i    = 0;

        $inStr         = false; // ' or "
        $strQuote      = '';
        $escape        = false;

        $inRegex       = false;
        $inRegexClass  = false; // inside [...]

        $inTpl         = false; // backtick template
        $tplExprDepth  = 0;     // depth of ${ ... } nesting

        $inLineComment = false; // //...
        $inBlockComment= false; // /*...*/
        $keepComment   = false; // /*! ... */

        $whitespace = ["\x20","\x0a","\x0d","\x09","\x0c","\x0b"];

        $isIdent = static function ($c): bool {
            return $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$');
        };
        $isDigit = static function ($c): bool { return $c !== '' && ctype_digit($c); };
        $isSpace = static function ($c) use ($whitespace): bool { return $c !== '' && in_array($c, $whitespace, true); };

        $lastSignificant = '';

        $peek = static function($s, $idx, $len, $off = 1): string {
            $j = $idx + $off;
            return ($j >= 0 && $j < $len) ? $s[$j] : '';
        };

        $append = static function(&$out, $ch, &$lastSignificant) use ($isSpace) {
            $out .= $ch;
            if (!$isSpace($ch)) {
                $lastSignificant = $ch;
            }
        };

        $needsSpaceBetween = static function($a, $b) use ($isIdent, $isDigit) : bool {
            if ($a === '' || $b === '') return false;

            // Prevent merging identifiers/numbers into a single token
            if (($isIdent($a) || $isDigit($a)) && ($isIdent($b) || $isDigit($b))) return true;

            // Avoid turning "+ +" -> "++" or "- -" -> "--"
            if (($a === '+' && $b === '+') || ($a === '-' && $b === '-')) return true;

            // Avoid "/ /" becoming comment accidentally (rare, but safe)
            if ($a === '/' && $b === '/') return true;

            // Keep space between number and identifier (e.g., 123 in -> 123in would be bad)
            if ($isDigit($a) && $isIdent($b)) return true;

            return false;
        };

        $canStartRegexAfter = static function($prev): bool {
            // Rough heuristic: regex can start after these or at BOL
            if ($prev === '') return true;
            return (bool)strpos("(,=:{[!&|?+-*%^~;<>", $prev);
        };

        while ($i < $len) {
            $ch = $js[$i];
            $nx = $peek($js, $i, $len, 1);

            // Handle end of line comment
            if ($inLineComment) {
                if ($ch === "\n") {
                    $inLineComment = false;
                    // collapse to single newline -> usually removable unless it separates tokens
                    // We'll just drop it and let whitespace logic decide later.
                }
                $i++; continue;
            }

            // Handle block comment
            if ($inBlockComment) {
                if ($ch === '*' && $nx === '/') {
                    $i += 2;
                    if ($keepComment) {
                        $append($out, '*/', $lastSignificant);
                    }
                    $inBlockComment = false; $keepComment = false;
                    continue;
                }
                if ($keepComment) {
                    $append($out, $ch, $lastSignificant);
                }
                $i++; continue;
            }

            // Inside a normal or template string
            if ($inStr) {
                if ($escape) {
                    $append($out, $ch, $lastSignificant);
                    $escape = false; $i++; continue;
                }
                if ($ch === '\\') {
                    $append($out, $ch, $lastSignificant);
                    $escape = true; $i++; continue;
                }
                $append($out, $ch, $lastSignificant);
                if ($ch === $strQuote) {
                    $inStr = false; $strQuote = '';
                }
                $i++; continue;
            }

            // Inside a regex literal
            if ($inRegex) {
                if ($escape) {
                    $append($out, $ch, $lastSignificant);
                    $escape = false; $i++; continue;
                }
                if ($ch === '\\') {
                    $append($out, $ch, $lastSignificant);
                    $escape = true; $i++; continue;
                }
                if ($ch === '[' && !$inRegexClass) {
                    $inRegexClass = true;
                    $append($out, $ch, $lastSignificant); $i++; continue;
                }
                if ($ch === ']' && $inRegexClass) {
                    $inRegexClass = false;
                    $append($out, $ch, $lastSignificant); $i++; continue;
                }
                $append($out, $ch, $lastSignificant);
                if ($ch === '/' && !$inRegexClass) {
                    // End of regex; now consume flags [a-z]*
                    $inRegex = false;
                    $j = $i + 1;
                    while ($j < $len) {
                        $c = $js[$j];
                        if (!preg_match('/[a-z]/i', $c)) break;
                        $append($out, $c, $lastSignificant);
                        $j++;
                    }
                    $i = $j;
                } else {
                    $i++;
                }
                continue;
            }

            // Inside a template literal (outside ${...} expressions)
            if ($inTpl) {
                if ($escape) {
                    $append($out, $ch, $lastSignificant);
                    $escape = false; $i++; continue;
                }
                if ($ch === '\\') {
                    $append($out, $ch, $lastSignificant);
                    $escape = true; $i++; continue;
                }
                if ($ch === '`') {
                    $append($out, $ch, $lastSignificant);
                    $inTpl = false; $i++; continue;
                }
                if ($ch === '$' && $nx === '{') {
                    $append($out, '${', $lastSignificant);
                    $tplExprDepth = 1; $i += 2; // enter expression mode
                    // From now, parse as normal JS until depth returns to 0
                    while ($i < $len && $tplExprDepth > 0) {
                        $c  = $js[$i];
                        $nn = $peek($js, $i, $len, 1);

                        // Manage strings inside ${}
                        if ($c === '"' || $c === "'" ) {
                            $inStr = true; $strQuote = $c; $append($out, $c, $lastSignificant); $i++;
                            // consume string fully
                            while ($i < $len && $inStr) {
                                $sc = $js[$i];
                                if ($escape) { $append($out, $sc, $lastSignificant); $escape=false; $i++; continue; }
                                if ($sc === '\\') { $append($out, $sc, $lastSignificant); $escape=true; $i++; continue; }
                                $append($out, $sc, $lastSignificant);
                                if ($sc === $strQuote) { $inStr=false; $strQuote=''; }
                                $i++;
                            }
                            continue;
                        }
                        if ($c === '`') { // nested template in expr
                            $inTpl = true; $append($out, $c, $lastSignificant); $i++;
                            // Let outer loop handle nested template
                            break;
                        }
                        if ($c === '{') { $append($out, $c, $lastSignificant); $tplExprDepth++; $i++; continue; }
                        if ($c === '}') { $append($out, $c, $lastSignificant); $tplExprDepth--; $i++; continue; }

                        // remove comments within ${}
                        if ($c === '/' && $nn === '/') { // line comment
                            $i += 2; while ($i < $len && $js[$i] !== "\n") { $i++; } continue;
                        }
                        if ($c === '/' && $nn === '*') { // block comment
                            $keep = $preserveImportant && ($peek($js, $i, $len, 2) === '!');
                            $i += 2; if ($keep) { $out .= '/*'; }
                            while ($i < $len) {
                                if ($js[$i] === '*' && $peek($js,$i,$len,1) === '/') { $i += 2; if ($keep) { $out .= '*/'; } break; }
                                if ($keep) { $out .= $js[$i]; }
                                $i++;
                            }
                            continue;
                        }

                        // whitespace collapsing inside ${}
                        if (preg_match('/\s/', $c)) {
                            // find prev and next significant chars (within output and input)
                            $prev = self::lastNonSpaceChar($out);
                            $k = $i + 1; while ($k < $len && preg_match('/\s/', $js[$k])) { $k++; }
                            $nxt = ($k < $len) ? $js[$k] : '';
                            if ($aggressive || !$aggressive) { // same action currently
                                if ($prev !== '' && $nxt !== '' && $prev !== '\n') {
                                    if (self::needsSpaceStatic($prev, $nxt)) { $out .= ' '; }
                                }
                            }
                            $i = $k; continue;
                        }

                        $append($out, $c, $lastSignificant); $i++;
                    }
                    continue;
                }
                $append($out, $ch, $lastSignificant);
                $i++; continue;
            }

            // Detect start of comments (when not in any literal)
            if ($ch === '/' && $nx === '/') {
                $inLineComment = true; $i += 2; continue;
            }
            if ($ch === '/' && $nx === '*') {
                $keepComment = false;
                if ($preserveImportant && $peek($js, $i, $len, 2) === '!') {
                    $keepComment = true;
                    $append($out, '/*!', $lastSignificant);
                    $i += 3; $inBlockComment = true; continue;
                }
                $inBlockComment = true; $i += 2; continue;
            }

            // Strings
            if ($ch === '"' || $ch === "'") {
                $inStr = true; $strQuote = $ch; $append($out, $ch, $lastSignificant); $i++; continue;
            }

            // Template literal start
            if ($ch === '`') {
                $inTpl = true; $append($out, $ch, $lastSignificant); $i++; continue;
            }

            // Possible regex vs division
            if ($ch === '/') {
                if ($canStartRegexAfter($lastSignificant)) {
                    $inRegex = true; $inRegexClass = false; $append($out, $ch, $lastSignificant); $i++; continue;
                }
                // else treat as division operator
                $append($out, $ch, $lastSignificant); $i++; continue;
            }

            // Whitespace handling (outside literals/comments)
            if ($isSpace($ch)) {
                // Look ahead to next non-space
                $j = $i + 1;
                while ($j < $len && $isSpace($js[$j])) { $j++; }
                $nextNon = ($j < $len) ? $js[$j] : '';
                $prevNon = self::lastNonSpaceChar($out);

                if ($prevNon !== '' && $nextNon !== '') {
                    if (self::needsSpaceStatic($prevNon, $nextNon)) {
                        $out .= ' ';
                    }
                }
                $i = $j;
                continue;
            }

            // Default: copy char; optionally trim around punctuation when aggressive
            if ($aggressive) {
                // remove space before certain punctuation already handled by whitespace branch
            }

            $append($out, $ch, $lastSignificant);
            $i++;
        }

        return $out;
    }

    private static function lastNonSpaceChar(string $out): string
    {
        for ($k = strlen($out) - 1; $k >= 0; $k--) {
            if (!preg_match('/\s/', $out[$k])) return $out[$k];
        }
        return '';
    }

    private static function needsSpaceStatic(string $a, string $b): bool
    {
        // Keep in sync with $needsSpaceBetween logic above, but static context
        $isIdent = static function ($c): bool { return $c !== '' && (ctype_alnum($c) || $c === '_' || $c === '$'); };
        $isDigit = static function ($c): bool { return $c !== '' && ctype_digit($c); };

        if ($a === '' || $b === '') return false;
        if (($isIdent($a) || $isDigit($a)) && ($isIdent($b) || $isDigit($b))) return true;
        if (($a === '+' && $b === '+') || ($a === '-' && $b === '-')) return true;
        if ($a === '/' && $b === '/') return true;
        if ($isDigit($a) && $isIdent($b)) return true;
        return false;
    }
}

// // --------------------
// // Usage example
// // --------------------
// if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
//     $src = <<<JS
// // Example
// function add ( a , b ) { // sum
//     return a +  b ;
// }
// const rx = /ab+c[def]*\//ig; // regex with /
// const tpl = `Hello ${ add(1,2) }!`;
// console.log(add( 1 ,  3 ));
// /*! keep this important comment */
// /* drop normal block comment */
// JS;

//     $min = JsMinify::emit($src, [
//         'preserve_important' => true,
//         'aggressive' => false,
//     ]);

//     fwrite(STDOUT, $min . "\n");
// }
