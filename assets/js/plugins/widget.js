/**
 * Plugin ini mencakup:
 * .arrayToTable – konversi array ke tabel HTML
 * .xhtml – sanitasi HTML pakai DOMPurify
 * .xbind – helper off/on event
 * $.sessionHandler – CRUD local/sessionStorage
 * $.indexedDBHandler – CRUD IndexedDB
 * $.cookieHandler – CRUD cookies
 * $.md5 – hash string jadi MD5
 * $.base64.encode/decode – encode/decode base64 
 */
(function($) {
    /** Array to Table just for icon list
     * $('#icon-table').arrayToTable(icons, { columnsPerRow: 8 });
    */
    $.fn.arrayToTable = function(data, options) {
        var settings = $.extend({
            columnsPerRow: 6,       // Default jumlah kolom per baris
            allRowInsert: true,     // Jika false, semua baris masuk ke tbody
            filterData: null
        }, options);

        return this.each(function() {
            var $table = $(this);
            var $tableHead = $table.find('thead');
            var $tableBody = $table.find('tbody');

            // Reset isi tabel
            $tableHead.empty();
            $tableBody.empty();

            var row = $('<tr></tr>');
            var num = 0;
            var cell, fdata;

            $.each(data, function(index, value) {
                // Jalankan filterData jika disediakan, jika tidak gunakan value asli
                fdata = (typeof settings.filterData !== 'function') ? value : settings.filterData(index, value);

                // Jika allRowInsert true, baris pertama ke thead, sisanya ke tbody
                if (settings.allRowInsert && num === 0) {
                    cell = $('<th></th>').html(fdata);
                } else {
                    cell = $('<td></td>').html(fdata);
                }

                row.append(cell);

                if ((index + 1) % settings.columnsPerRow === 0) {
                    if (settings.allRowInsert && num === 0) {
                        $tableHead.append(row);
                    } else {
                        $tableBody.append(row);
                    }
                    row = $('<tr></tr>');
                    num++;
                }
            });

            // Jika ada sisa baris terakhir
            if (row.children().length > 0) {
                var remainingCells = settings.columnsPerRow - row.children().length;

                for (var i = 0; i < remainingCells; i++) {
                    var emptyCell = (settings.allRowInsert && num === 0) ? $('<th></th>').html('&nbsp;') : $('<td></td>').html('&nbsp;');
                    row.append(emptyCell);
                }

                if (settings.allRowInsert && num === 0) {
                    $tableHead.append(row);
                } else {
                    $tableBody.append(row);
                }
            }
        });
    };
    // anti XSS dan even On Off for jquery 1.8.3
    // https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js
    // Contoh Penggunaan
    // $(".target").xhtml('<img src="x" onerror="alert(\'XSS\')">');
    // $(".target").xbind('click',function(e){});
    $.fn.xbind = function(...events) {
        return this.each(function() {
            $(this).off(events[0]).on(...events);
        })
    };
    $.fn.xhtml = function(source) {

        function sanitizeAndRender(element) {
            var cleanInput = DOMPurify.sanitize(source);
            $(element).html(cleanInput);
        }

        function loadDOMPurify(callback) {
            if (typeof DOMPurify === 'undefined') {
                $.getScript('https://cdnjs.cloudflare.com/ajax/libs/dompurify/2.4.0/purify.min.js')
                    .done(function() {
                        callback();
                    })
                    .fail(function() {
                        console.error('Failed to load DOMPurify.');
                    });
            } else {
                callback();
            }
        }

        return this.each(function() {
            var element = this; // Ini DOM element yang spesifik
            loadDOMPurify(function() {
                sanitizeAndRender(element);
            });
        });

    };
    // CRUD SESSION LOGIN
    // Contoh Penggunaan
    // var session = $.sessionHandler({
    //     storageType: 'local'
    // });
    // session.set('isLogin', true);
    // session.set('user', {name: 'Riza', email: 'riza@example.com'});
    // session.set('token', 'h89879hy9891h298u02193j9');
    // session.remove('isLogin');
    // session.clearAll();
    // var isLogin = session.get('isLogin');
    // var userData = session.get('user');
    // var token = session.get('token');
    // console.log(isLogin,userData,token);

    $.sessionHandler = function(options) {
        var defaults = {
            storageType: 'session', // 'session' atau 'local'
            prefix: 'app_' // untuk menghindari tabrakan key
        };

        var settings = $.extend({}, defaults, options);

        var storage = (settings.storageType === 'local') ? localStorage : sessionStorage;

        return {
            set: function(key, value) {
                storage.setItem(settings.prefix + key, JSON.stringify(value));
            },
            get: function(key) {
                var item = storage.getItem(settings.prefix + key);
                return item ? JSON.parse(item) : null;
            },
            remove: function(key) {
                storage.removeItem(settings.prefix + key);
            },
            clearAll: function() {
                for (var i = storage.length - 1; i >= 0; i--) {
                    var k = storage.key(i);
                    if (k.startsWith(settings.prefix)) {
                        storage.removeItem(k);
                    }
                }
            }
        };
    };
    // CRUD indexed DB
    // var db = $.indexedDBHandler({ dbName: 'MyApp', storeName: 'users' });
    // db.init(function() {
    // db.add({ id: 1, name: 'Riza' });
    // db.get(1, console.log);
    // });
    $.indexedDBHandler = function(options) {
        var defaults = {
            dbName: 'AppDB',
            storeName: 'defaultStore',
            version: 1,
            keyPath: 'id',
            autoIncrement: true
        };

        var settings = $.extend({}, defaults, options);
        var db;

        function init(callback) {
            var request = indexedDB.open(settings.dbName, settings.version);

            request.onupgradeneeded = function(e) {
                db = e.target.result;
                if (!db.objectStoreNames.contains(settings.storeName)) {
                    db.createObjectStore(settings.storeName, {
                    keyPath: settings.keyPath,
                    autoIncrement: settings.autoIncrement
                    });
                }
            };

            request.onsuccess = function(e) {
                db = e.target.result;
                if (typeof callback === 'function') callback();
            };

            request.onerror = function(e) {
                console.error("IndexedDB Error:", e.target.error);
            };
        }

        function getStore(mode) {
            return db.transaction([settings.storeName], mode).objectStore(settings.storeName);
        }

        return {
            init: init,

            add: function(data, callback) {
                var request = getStore('readwrite').add(data);
                request.onsuccess = function() { if (callback) callback(true); };
                request.onerror = function() { if (callback) callback(false); };
            },

            get: function(key, callback) {
                var request = getStore('readonly').get(key);
                request.onsuccess = function(e) { if (callback) callback(e.target.result); };
            },

            update: function(data, callback) {
                var request = getStore('readwrite').put(data);
                request.onsuccess = function() { if (callback) callback(true); };
                request.onerror = function() { if (callback) callback(false); };
            },

            delete: function(key, callback) {
                var request = getStore('readwrite').delete(key);
                request.onsuccess = function() { if (callback) callback(true); };
            },

            getAll: function(callback) {
                var request = getStore('readonly').getAll();
                request.onsuccess = function(e) { if (callback) callback(e.target.result); };
            },

            clearAll: function(callback) {
                var request = getStore('readwrite').clear();
                request.onsuccess = function() { if (callback) callback(true); };
            }
        };
    };
    // CRUD Cookies 
    // var cookie = $.cookieHandler({ prefix: 'app_' });
    // cookie.set('token', 'abc123');
    // console.log(cookie.get('token'));
    // cookie.remove('token');
    $.cookieHandler = function(options) {
        var defaults = {
            prefix: 'app_',
            expires: 7 // days
        };
        var settings = $.extend({}, defaults, options);
        function setCookie(name, value, days) {
            var d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();
            document.cookie = settings.prefix + name + "=" + encodeURIComponent(JSON.stringify(value)) + ";" + expires + ";path=/";
        }
        function getCookie(name) {
            var cname = settings.prefix + name + "=";
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(cname) === 0) {
                try {
                return JSON.parse(c.substring(cname.length, c.length));
                } catch (e) {
                return null;
                }
            }
            }
            return null;
        }
        function deleteCookie(name) {
            document.cookie = settings.prefix + name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        }
        return {
            set: function(key, value, days) {
                setCookie(key, value, days || settings.expires);
            },
            get: function(key) {
                return getCookie(key);
            },
            remove: function(key) {
                deleteCookie(key);
            },
            clearAll: function() {
                var cookies = document.cookie.split(";");
                for (var i = 0; i < cookies.length; i++) {
                    var key = cookies[i].split("=")[0].trim();
                    if (key.startsWith(settings.prefix)) {
                    deleteCookie(key.replace(settings.prefix, ''));
                    }
                }
            }
        };
    };
    // md5 hash
    // console.log($.md5("hello world")); 
    $.md5 = function(string) {
        function RotateLeft(lValue, iShiftBits) {
        return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
        }

        function AddUnsigned(lX, lY) {
        var lX4, lY4, lX8, lY8, lResult;
        lX8 = (lX & 0x80000000);
        lY8 = (lY & 0x80000000);
        lX4 = (lX & 0x40000000);
        lY4 = (lY & 0x40000000);
        lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        if (lX4 | lY4) {
            if (lResult & 0x40000000) return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
            else return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
        } else return (lResult ^ lX8 ^ lY8);
        }

        function F(x, y, z) { return (x & y) | ((~x) & z); }
        function G(x, y, z) { return (x & z) | (y & (~z)); }
        function H(x, y, z) { return (x ^ y ^ z); }
        function I(x, y, z) { return (y ^ (x | (~z))); }

        function FF(a, b, c, d, x, s, ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
        }

        function GG(a, b, c, d, x, s, ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
        }

        function HH(a, b, c, d, x, s, ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
        }

        function II(a, b, c, d, x, s, ac) {
        a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
        return AddUnsigned(RotateLeft(a, s), b);
        }

        function ConvertToWordArray(str) {
        var lWordCount, lMessageLength = str.length;
        var lNumberOfWords_temp1 = lMessageLength + 8;
        var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
        var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
        var lWordArray = Array(lNumberOfWords - 1);
        var lBytePosition = 0, lByteCount = 0;
        while (lByteCount < lMessageLength) {
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] |= (str.charCodeAt(lByteCount) << lBytePosition);
            lByteCount++;
        }
        lWordCount = (lByteCount - (lByteCount % 4)) / 4;
        lBytePosition = (lByteCount % 4) * 8;
        lWordArray[lWordCount] |= (0x80 << lBytePosition);
        lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
        lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
        return lWordArray;
        }

        function WordToHex(lValue) {
        var WordToHexValue = "", WordToHexValue_temp = "", lByte, lCount;
        for (lCount = 0; lCount <= 3; lCount++) {
            lByte = (lValue >>> (lCount * 8)) & 255;
            WordToHexValue_temp = "0" + lByte.toString(16);
            WordToHexValue += WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
        }
        return WordToHexValue;
        }

        var x = [], k, AA, BB, CC, DD, a, b, c, d;
        var S11 = 7, S12 = 12, S13 = 17, S14 = 22;
        var S21 = 5, S22 = 9 , S23 = 14, S24 = 20;
        var S31 = 4, S32 = 11, S33 = 16, S34 = 23;
        var S41 = 6, S42 = 10, S43 = 15, S44 = 21;

        string = unescape(encodeURIComponent(string));
        x = ConvertToWordArray(string);
        a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;

        for (k = 0; k < x.length; k += 16) {
        AA = a; BB = b; CC = c; DD = d;
        a = FF(a, b, c, d, x[k+0],  S11, 0xD76AA478);
        a = FF(a, b, c, d, x[k+1],  S12, 0xE8C7B756);
        a = FF(a, b, c, d, x[k+2],  S13, 0x242070DB);
        a = FF(a, b, c, d, x[k+3],  S14, 0xC1BDCEEE);
        a = FF(a, b, c, d, x[k+4],  S11, 0xF57C0FAF);
        a = FF(a, b, c, d, x[k+5],  S12, 0x4787C62A);
        a = FF(a, b, c, d, x[k+6],  S13, 0xA8304613);
        a = FF(a, b, c, d, x[k+7],  S14, 0xFD469501);
        a = FF(a, b, c, d, x[k+8],  S11, 0x698098D8);
        a = FF(a, b, c, d, x[k+9],  S12, 0x8B44F7AF);
        a = FF(a, b, c, d, x[k+10], S13, 0xFFFF5BB1);
        a = FF(a, b, c, d, x[k+11], S14, 0x895CD7BE);
        a = FF(a, b, c, d, x[k+12], S11, 0x6B901122);
        a = FF(a, b, c, d, x[k+13], S12, 0xFD987193);
        a = FF(a, b, c, d, x[k+14], S13, 0xA679438E);
        a = FF(a, b, c, d, x[k+15], S14, 0x49B40821);
        b = GG(b, a, c, d, x[k+1],  S21, 0xF61E2562);
        b = GG(b, a, c, d, x[k+6],  S22, 0xC040B340);
        b = GG(b, a, c, d, x[k+11], S23, 0x265E5A51);
        b = GG(b, a, c, d, x[k+0],  S24, 0xE9B6C7AA);
        b = GG(b, a, c, d, x[k+5],  S21, 0xD62F105D);
        b = GG(b, a, c, d, x[k+10], S22, 0x2441453);
        b = GG(b, a, c, d, x[k+15], S23, 0xD8A1E681);
        b = GG(b, a, c, d, x[k+4],  S24, 0xE7D3FBC8);
        b = GG(b, a, c, d, x[k+9],  S21, 0x21E1CDE6);
        b = GG(b, a, c, d, x[k+14], S22, 0xC33707D6);
        b = GG(b, a, c, d, x[k+3],  S23, 0xF4D50D87);
        b = GG(b, a, c, d, x[k+8],  S24, 0x455A14ED);
        b = GG(b, a, c, d, x[k+13], S21, 0xA9E3E905);
        b = GG(b, a, c, d, x[k+2],  S22, 0xFCEFA3F8);
        b = GG(b, a, c, d, x[k+7],  S23, 0x676F02D9);
        b = GG(b, a, c, d, x[k+12], S24, 0x8D2A4C8A);
        c = HH(c, b, a, d, x[k+5],  S31, 0xFFFA3942);
        c = HH(c, b, a, d, x[k+8],  S32, 0x8771F681);
        c = HH(c, b, a, d, x[k+11], S33, 0x6D9D6122);
        c = HH(c, b, a, d, x[k+14], S34, 0xFDE5380C);
        c = HH(c, b, a, d, x[k+1],  S31, 0xA4BEEA44);
        c = HH(c, b, a, d, x[k+4],  S32, 0x4BDECFA9);
        c = HH(c, b, a, d, x[k+7],  S33, 0xF6BB4B60);
        c = HH(c, b, a, d, x[k+10], S34, 0xBEBFBC70);
        c = HH(c, b, a, d, x[k+13], S31, 0x289B7EC6);
        c = HH(c, b, a, d, x[k+0],  S32, 0xEAA127FA);
        c = HH(c, b, a, d, x[k+3],  S33, 0xD4EF3085);
        c = HH(c, b, a, d, x[k+6],  S34, 0x4881D05);
        c = HH(c, b, a, d, x[k+9],  S31, 0xD9D4D039);
        c = HH(c, b, a, d, x[k+12], S32, 0xE6DB99E5);
        c = HH(c, b, a, d, x[k+15], S33, 0x1FA27CF8);
        c = HH(c, b, a, d, x[k+2],  S34, 0xC4AC5665);
        d = II(d, c, b, a, x[k+0],  S41, 0xF4292244);
        d = II(d, c, b, a, x[k+7],  S42, 0x432AFF97);
        d = II(d, c, b, a, x[k+14], S43, 0xAB9423A7);
        d = II(d, c, b, a, x[k+5],  S44, 0xFC93A039);
        d = II(d, c, b, a, x[k+12], S41, 0x655B59C3);
        d = II(d, c, b, a, x[k+3],  S42, 0x8F0CCC92);
        d = II(d, c, b, a, x[k+10], S43, 0xFFEFF47D);
        d = II(d, c, b, a, x[k+1],  S44, 0x85845DD1);
        d = II(d, c, b, a, x[k+8],  S41, 0x6FA87E4F);
        d = II(d, c, b, a, x[k+15], S42, 0xFE2CE6E0);
        d = II(d, c, b, a, x[k+6],  S43, 0xA3014314);
        d = II(d, c, b, a, x[k+13], S44, 0x4E0811A1);
        d = II(d, c, b, a, x[k+4],  S41, 0xF7537E82);
        d = II(d, c, b, a, x[k+11], S42, 0xBD3AF235);
        d = II(d, c, b, a, x[k+2],  S43, 0x2AD7D2BB);
        d = II(d, c, b, a, x[k+9],  S44, 0xEB86D391);
        a = AddUnsigned(a, AA);
        b = AddUnsigned(b, BB);
        c = AddUnsigned(c, CC);
        d = AddUnsigned(d, DD);
        }

        var temp = WordToHex(a) + WordToHex(b) + WordToHex(c) + WordToHex(d);
        return temp.toLowerCase();
    };
    // base64 encode / decode
    $.base64 = {
        encode: function(str) {
            return btoa(unescape(encodeURIComponent(str)));
        },
        decode: function(str) {
            return decodeURIComponent(escape(atob(str)));
        }
    };
})(jQuery);