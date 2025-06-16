(function($) {
    'use strict';

    /**
     * Helper function to create and submit a form for a given action.
     * @param {string} action - The action to perform (e.g., 'add_token').
     * @param {object} data - An object of data to include in the form.
     */
    function submitAction(action, data) {
        const form = $('<form>', { method: 'post', action: window.location.href });

        // Add nonce and action to the form
        form.append($('<input>', { type: 'hidden', name: '_token_nonce', value: customTokens.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'token_action', value: action }));

        // Add all other data fields to the form
        $.each(data, function(key, value) {
            form.append($('<input>', { type: 'hidden', name: key, value: value }));
        });

        $('body').append(form);
        form.submit();
    }

    $(function() {
        // --- REMOVE TOKEN ---
        $(document).on('click', '.remove-token-btn', function(e) {
            e.preventDefault();
            const tokenName = $(this).data('token');
            if (confirm('Are you sure you want to remove the token "' + tokenName + '"?')) {
                submitAction('remove_token', { 'remove_token_name': tokenName });
            }
        });

        // --- ADD TOKEN ---
        $('#add_token_btn').on('click', function(e) {
            e.preventDefault();
            const name = $('#new_token_name').val().trim();
            const label = $('#new_token_label').val().trim();
            const value = $('#new_token_value').val().trim();

            if (!name || !label) {
                alert('Please provide both a Token Name and a Token Label.');
                return;
            }
            if (!/^[a-zA-Z0-9_]+$/.test(name)) {
                alert('Token Name can only contain letters, numbers, and underscores.');
                return;
            }

            submitAction('add_token', {
                'new_token[name]': name,
                'new_token[label]': label,
                'new_token[value]': value
            });
        });

        // --- IMPORT TOKENS ---
        $('#import_tokens_btn').on('click', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('token_import_file');
            if (!fileInput.files[0]) {
                alert('Please select a JSON file to import.');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(event) {
                try {
                    const data = JSON.parse(event.target.result);
                    if (!data.tokens || !Array.isArray(data.tokens)) {
                        throw new Error('Invalid JSON format. File must contain a "tokens" array.');
                    }
                    const importPayload = {
                        tokens: data.tokens,
                        replace_existing: $('#replace_existing').is(':checked')
                    };
                    submitAction('import_tokens', { 'import_tokens_data': JSON.stringify(importPayload) });
                } catch (error) {
                    alert('Error parsing JSON file: ' + error.message);
                }
            };
            reader.readAsText(fileInput.files[0]);
        });

        // --- EXPORT TOKENS ---
        $('#export_tokens_btn').on('click', function(e) {
            e.preventDefault();
            const exportData = { tokens: customTokens.export_data };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(exportData, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", `tokens_export_${new Date().toISOString().split('T')[0]}.json`);
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        });
    });

})(jQuery);