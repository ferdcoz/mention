(function () {
    'use strict';
    var background = document.getElementById('simple_mention_background');
    var text = document.getElementById('simple_mention_text');
    var font = document.getElementById('simple_mention_style');
    var preview = document.getElementById('mention_tag_preview');
    var warning = document.getElementById('mention_preview_invalid');
    if (!background || !text || !font || !preview) { return; }
    function colour(value) {
        value = value.trim().replace(/^#/, '');
        return /^(?:[a-f0-9]{3}|[a-f0-9]{6})$/i.test(value) ? '#' + value : null;
    }
    function refresh() {
        var bg = background.value.trim() === '' ? 'var(--main-color, #536482)' : colour(background.value);
        var fg = colour(text.value);
        background.setAttribute('aria-invalid', bg ? 'false' : 'true');
        text.setAttribute('aria-invalid', fg ? 'false' : 'true');
        warning.hidden = !!(bg && fg);
        if (bg) {
            preview.style.backgroundColor = bg;
            document.getElementById('mention_background_swatch').style.backgroundColor = bg;
        }
        if (fg) {
            preview.style.color = fg;
            document.getElementById('mention_text_swatch').style.backgroundColor = fg;
        }
        preview.style.fontStyle = font.value.indexOf('italic') !== -1 ? 'italic' : 'normal';
        preview.style.fontWeight = font.value.indexOf('bold') !== -1 ? 'bold' : '500';
    }
    [background, text].forEach(function (input) { input.addEventListener('input', refresh); });
    font.addEventListener('change', refresh);
    document.getElementById('acp_mention_settings').addEventListener('reset', function () { window.setTimeout(refresh, 0); });
    refresh();
}());
