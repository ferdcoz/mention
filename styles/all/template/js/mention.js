$(document).ready(function () {
    var timer = null, pending = null, generation = 0;
    function remoteSearch(query, callback) {
        clearTimeout(timer);
        var current = ++generation;
        if (pending) { pending.abort(); pending = null; }
        if (query.length < MIN_MENTION_LENGTH) {
            callback([]);
            return;
        }
        timer = setTimeout(function () {
            pending = $.getJSON(U_AJAX_MENTION_URL, {q: query}, function (data) {
                if (current === generation) { callback(data); }
            }).fail(function () { if (current === generation) { callback([]); } });
        }, 300);
    }

    function escapeHtml(value) {
        return $('<span>').text(value).html();
    }
    tribute = new Tribute({
        collection: [{
            trigger: '@',
			requireLeadingSpace: true, // Prevents triggering on email addresses, but allows the start of a sentence
            menuItemTemplate: function (item) {
                if (item.original.type === 'group') {
                    return escapeHtml(item.original.value) +  SIMPLE_MENTION_GROUP_NAME.replace('{CNT}', item.original.cnt) ;
                }
                return escapeHtml(item.original.value);
            },

            selectTemplate: function (item) {
                if (item.original.type === 'user') {
                    return '[smention u=' + item.original.user_id + ']' + item.original.value + '[/smention]';
                }
                else if (item.original.type === 'group') {
                    if (item.original.cnt > MENTION_LARGE_THRESHOLD && !window.confirm(MENTION_CONFIRM_GROUP.replace('{CNT}', item.original.cnt))) { return null; }
                    return '[smention g=' + item.original.group_id + ']' + item.original.value + '[/smention]';
                }
            },
            values: remoteSearch,
            spaceSelectsMatch: true,
            lookup: 'value',
            searchOpts: {skip: true}, // Server filtering preserves participant priority.
        }]
    });
    var messages = document.querySelectorAll('[name="message"]');
    tribute.attach(messages);
    messages.forEach(function (element) {
        // Tribute's commandEvent can stay set after Backspace/Delete/cut.
        // Refresh from the actual input, including repeated deletion and paste.
        element.addEventListener('input', function (event) {
            tribute.events.commandEvent = false;
            tribute.events.keyup.call(this, tribute.events, event);
        });
    });
});
