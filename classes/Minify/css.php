<?php
namespace Minify;
/**
 * Simple CSS Minifier in pure PHP 7.4+
 * ------------------------------------
 * - Hapus komentar /* ... *\/
 * - Bisa pertahankan /*! ... *\/ jika $options['preserve_important'] = true
 * - Hilangkan whitespace berlebih
 * - Hapus spasi di sekitar { } : ; , > dan ; sebelum }
 */

class css
{
    /**
     * Minify CSS code.
     *
     * @param string $css
     * @param array  $options
     *   - preserve_important (bool): default true
     * @return string
     */
    public static function emit(string $css, array $options = []): string
    {
        $preserveImportant = $options['preserve_important'] ?? true;

        // Hapus komentar (kecuali /*! ... */ jika preserve_important = true)
        $css = preg_replace_callback(
            '#/\*.*?\*/#s',
            function ($m) use ($preserveImportant) {
                if ($preserveImportant && substr($m[0], 0, 3) === '/*!') {
                    return $m[0]; // biarkan
                }
                return ''; // hapus
            },
            $css
        );

        // Hilangkan whitespace berlebih
        $css = preg_replace('/\s+/', ' ', $css);

        // Hapus spasi di sekitar tanda tertentu
        $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css);

        // Hilangkan titik koma sebelum penutup }
        $css = preg_replace('/;}/', '}', $css);

        return trim($css);
    }
}

// // --------------------
// // Contoh pemakaian
// // --------------------
// if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0] ?? '')) {
//     $src = <<<CSS
// /* normal comment */
// body {
//     margin : 0 ;
//     padding: 0 ; /* reset */
// }
// /*! keep this license */
// h1 {
//     color: red ;
// }
// CSS;

//     $min = cssMinify::emit($src, [
//         'preserve_important' => true
//     ]);

//     echo $min . PHP_EOL;
// }
