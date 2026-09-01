/*
 * Theme-Editor: Live-Vorschau + Kontrastwarnungen.
 *
 * Die Vorschau (#themePreview) bekommt alle Basis-Tokens als Inline-Style.
 * Die abgeleiteten Tokens (--surface-veil …) sind per color-mix aus den
 * Basis-Tokens definiert und folgen automatisch. --color-on-* (Schrift auf
 * farbiger Fläche) wird hier berechnet – wie serverseitig in readable_ink().
 */
(function () {
    'use strict';

    const root = document.getElementById('themeEditor');
    const preview = document.getElementById('themePreview');
    if (!root || !preview) {
        return;
    }

    // --- Farb-Hilfen -------------------------------------------------------

    /** Parst #rgb, #rrggbb, rgb(), rgba(). Gibt {r,g,b,a} oder null. */
    function parseColor(value) {
        if (typeof value !== 'string') {
            return null;
        }
        const v = value.trim();

        let m = v.match(/^#([0-9a-f]{3})$/i);
        if (m) {
            return {
                r: parseInt(m[1][0] + m[1][0], 16),
                g: parseInt(m[1][1] + m[1][1], 16),
                b: parseInt(m[1][2] + m[1][2], 16),
                a: 1,
            };
        }

        m = v.match(/^#([0-9a-f]{6})$/i);
        if (m) {
            return {
                r: parseInt(m[1].slice(0, 2), 16),
                g: parseInt(m[1].slice(2, 4), 16),
                b: parseInt(m[1].slice(4, 6), 16),
                a: 1,
            };
        }

        m = v.match(/^rgba?\(\s*([0-9.]+)[\s,]+([0-9.]+)[\s,]+([0-9.]+)(?:[\s,/]+([0-9.]+%?))?\s*\)$/i);
        if (m) {
            let a = 1;
            if (m[4] !== undefined && m[4] !== '') {
                a = m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]);
            }
            return {
                r: parseFloat(m[1]),
                g: parseFloat(m[2]),
                b: parseFloat(m[3]),
                a: Math.max(0, Math.min(1, a)),
            };
        }

        return null;
    }

    /** Legt fg (mit Alpha) über bg (opak). */
    function composite(fg, bg) {
        return {
            r: fg.r * fg.a + bg.r * (1 - fg.a),
            g: fg.g * fg.a + bg.g * (1 - fg.a),
            b: fg.b * fg.a + bg.b * (1 - fg.a),
            a: 1,
        };
    }

    const WHITE = { r: 255, g: 255, b: 255, a: 1 };

    /** Löst einen Tokenwert zu einer opaken Farbe auf (über Kette von Hintergründen). */
    function resolveOpaque(value, backdrops) {
        const c = parseColor(value);
        if (!c) {
            return null;
        }
        if (c.a >= 1) {
            return c;
        }
        let bg = WHITE;
        for (let i = backdrops.length - 1; i >= 0; i--) {
            const parsed = parseColor(backdrops[i]);
            if (parsed) {
                bg = parsed.a >= 1 ? parsed : composite(parsed, bg);
            }
        }
        return composite(c, bg);
    }

    function channel(value) {
        const c = value / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }

    function luminance(rgb) {
        return 0.2126 * channel(rgb.r) + 0.7152 * channel(rgb.g) + 0.0722 * channel(rgb.b);
    }

    function contrast(a, b) {
        const la = luminance(a);
        const lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    /** Schwarz oder Weiß – was den höheren Kontrast auf bg liefert. */
    function readableInk(bg) {
        const dark = { r: 24, g: 26, b: 21, a: 1 };
        const light = { r: 255, g: 255, b: 255, a: 1 };
        return contrast(dark, bg) >= contrast(light, bg) ? dark : light;
    }

    function toCss(rgb) {
        return 'rgb(' + Math.round(rgb.r) + ', ' + Math.round(rgb.g) + ', ' + Math.round(rgb.b) + ')';
    }

    // --- Felder einsammeln -----------------------------------------------

    const fields = Array.prototype.slice.call(root.querySelectorAll('[data-token]'));
    const byKey = {};
    fields.forEach(function (input) {
        byKey[input.dataset.token] = input;
    });

    function currentValue(key) {
        const input = byKey[key];
        if (!input) {
            return '';
        }
        const val = input.value.trim();
        return val !== '' ? val : (input.getAttribute('placeholder') || '');
    }

    // --- Kontrast-Prüfungen ---------------------------------------------
    // key -> Funktion, die {ratio, min, label} liefert (oder null = keine Prüfung).

    const CHECKS = {
        color_text: function (v) {
            return check(v, ['color_bg', 'color_surface'], 4.5, 'auf Flächen');
        },
        color_muted: function (v) {
            return check(v, ['color_bg', 'color_surface'], 4.5, 'auf Kartenfläche');
        },
        color_primary_strong: function (v) {
            return check(v, ['color_bg', 'color_surface'], 4.5, 'als Linkfarbe');
        },
        color_primary: function (v) {
            return checkAutoInk(v, 'Knopfschrift');
        },
        color_accent: function (v) {
            return checkAutoInk(v, 'Schrift auf Kopfleiste');
        },
        color_danger: function (v) {
            const asText = check(v, ['color_bg', 'color_surface'], 4.5, 'als Warntext');
            const asBtn = checkAutoInk(v, 'Knopfschrift');
            if (!asText && !asBtn) {
                return null;
            }
            if (asText && asBtn) {
                return asText.ratio < asBtn.ratio ? asText : asBtn;
            }
            return asText || asBtn;
        },
        color_success: function (v) {
            return check(v, ['color_bg', 'color_surface'], 4.5, 'als Erfolgstext');
        },
        // color_border wird bewusst nicht geprüft: die Rahmen sind gewollt
        // dezent (< 3:1) und werden durch Flächen-/Schattenunterschiede ergänzt.
    };

    function check(value, backdropKeys, min, label, nonText) {
        const backdrops = backdropKeys.map(currentValue);
        const fg = resolveOpaque(value, backdrops.concat(['#ffffff']));
        const bgKey = backdropKeys[backdropKeys.length - 1];
        const bg = resolveOpaque(currentValue(bgKey), ['#ffffff']);
        if (!fg || !bg) {
            return null;
        }
        const ratio = contrast(fg, bg);
        return { ratio: ratio, min: min, label: label, nonText: !!nonText };
    }

    function checkAutoInk(value, label) {
        const bg = resolveOpaque(value, ['#ffffff']);
        if (!bg) {
            return null;
        }
        const ink = readableInk(bg);
        const ratio = contrast(ink, bg);
        return { ratio: ratio, min: 4.5, label: label };
    }

    // --- Vorschau + Warnungen aktualisieren -----------------------------

    const BASE_KEYS = fields.map(function (f) { return f.dataset.token; });

    function apply() {
        const style = preview.style;

        BASE_KEYS.forEach(function (key) {
            const def = byKey[key];
            const cssVar = def.dataset.cssvar;
            style.setProperty(cssVar, currentValue(key));
        });

        // Auto-Kontrastfarben berechnen.
        [
            ['color_primary', '--color-on-primary'],
            ['color_danger', '--color-on-danger'],
            ['color_accent', '--color-on-accent'],
        ].forEach(function (pair) {
            const bg = resolveOpaque(currentValue(pair[0]), ['#ffffff']);
            style.setProperty(pair[1], bg ? toCss(readableInk(bg)) : '#181a15');
        });

        // Kontrastwarnungen.
        fields.forEach(function (input) {
            const key = input.dataset.token;
            const note = input.closest('.token-field').querySelector('[data-contrast]');
            if (!note) {
                return;
            }
            const fn = CHECKS[key];
            const result = fn ? fn(currentValue(key)) : null;
            if (!result) {
                note.textContent = '';
                note.hidden = true;
                note.classList.remove('is-warn');
                return;
            }
            const rounded = result.ratio.toFixed(1);
            note.hidden = false;
            if (result.ratio + 0.05 < result.min) {
                note.classList.add('is-warn');
                note.textContent = 'Kontrast ' + rounded + ' ' + result.label + ' – unter ' + result.min + ':1';
            } else {
                note.classList.remove('is-warn');
                note.textContent = 'Kontrast ' + rounded + ' ' + result.label + ' ✓';
            }
        });
    }

    // --- Farbwähler <-> Textfeld ---------------------------------------

    root.querySelectorAll('[data-color-picker]').forEach(function (picker) {
        const key = picker.dataset.colorPicker;
        const text = byKey[key];
        if (!text) {
            return;
        }

        picker.addEventListener('input', function () {
            text.value = picker.value;
            text.dispatchEvent(new Event('input', { bubbles: true }));
        });

        text.addEventListener('input', function () {
            const parsed = parseColor(text.value.trim() || text.getAttribute('placeholder') || '');
            if (parsed && parsed.a >= 1) {
                picker.value = '#' + [parsed.r, parsed.g, parsed.b]
                    .map(function (n) { return Math.round(n).toString(16).padStart(2, '0'); })
                    .join('');
            }
        });
    });

    fields.forEach(function (input) {
        input.addEventListener('input', apply);
    });

    apply();
})();
