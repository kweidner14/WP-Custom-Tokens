(function($) {
    'use strict';

    /**
     * Helper function to create and submit a form for a given action.
     * @param {string} action - The action to perform (e.g., 'add_token').
     * @param {object} data - An object of data to include in the form.
     */
    function submitAction(action, data) {
        const form = $('<form>', { method: 'post', action: window.location.href });
        form.append($('<input>', { type: 'hidden', name: '_token_nonce', value: customTokens.nonce }));
        form.append($('<input>', { type: 'hidden', name: 'token_action', value: action }));
        $.each(data, function(key, value) {
            form.append($('<input>', { type: 'hidden', name: key, value: value }));
        });
        $('body').append(form);
        form.submit();
    }

    /**
     * Helper function to detect file format based on extension.
     * @param {string} filename - The filename to check.
     * @returns {string} - 'json' or 'csv'.
     */
    function getFileFormat(filename) {
        const extension = filename.split('.').pop().toLowerCase();
        return extension === 'csv' ? 'csv' : 'json';
    }

    /**
     * Triggers a browser file download.
     * @param {string} content - The content of the file.
     * @param {string} mimeType - The MIME type of the file (e.g., 'data:text/json;charset=utf-8,').
     * @param {string} fileName - The desired name for the downloaded file.
     */
    function triggerDownload(content, mimeType, fileName) {
        const dataStr = mimeType + encodeURIComponent(content);
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute('href', dataStr);
        downloadAnchorNode.setAttribute('download', fileName);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }

    /**
     * Parse CSV content into an array of token objects.
     * Handles both `\n` and `\r\n` line endings.
     * @param {string} csvContent - The CSV content as a string.
     * @returns {Array} - Array of token objects.
     */
    function parseCSV(csvContent) {
        // Use a regex to split by newlines, accommodating both Windows and Unix-style line endings.
        const lines = csvContent.split(/\r?\n/).filter(line => line.trim());
        const tokens = [];

        // Check for header row and skip if it exists.
        let startIndex = 0;
        if (lines[0] && lines[0].toLowerCase().includes('name')) {
            startIndex = 1;
        }

        for (let i = startIndex; i < lines.length; i++) {
            const row = parseCSVLine(lines[i]);
            if (row.length >= 2 && row[0].trim()) {
                tokens.push({
                    name: row[0].trim(),
                    label: row[1].trim(),
                    value: row[2] ? row[2].trim() : ''
                });
            }
        }
        return tokens;
    }

    /**
     * Parse a single CSV line, handling quoted values.
     * @param {string} line - CSV line to parse.
     * @returns {Array} - Array of values.
     */
    function parseCSVLine(line) {
        const result = [];
        let current = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                // Handles escaped quotes ("") inside a quoted field.
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                result.push(current);
                current = '';
            } else {
                current += char;
            }
        }
        result.push(current);
        return result;
    }

    $(function() {
        const $importBtn = $('#import_tokens_btn');
        const $fileInput = $('#token_import_file');

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
        $importBtn.on('click', function(e) {
            e.preventDefault();

            if (!$fileInput[0].files.length) {
                alert('Please select a JSON or CSV file to import.');
                return;
            }

            // Disable button to prevent multiple submissions.
            $importBtn.prop('disabled', true).text('Importing...');

            const file = $fileInput[0].files[0];
            const format = getFileFormat(file.name);
            const reader = new FileReader();

            reader.onload = function(event) {
                try {
                    let tokens;
                    if (format === 'csv') {
                        tokens = parseCSV(event.target.result);
                        if (tokens.length === 0) {
                            throw new Error('No valid tokens found in CSV file. Expected format: name,label,value');
                        }
                    } else {
                        const data = JSON.parse(event.target.result);
                        if (!data.tokens || !Array.isArray(data.tokens)) {
                            throw new Error('Invalid JSON format. File must contain a "tokens" array.');
                        }
                        tokens = data.tokens;
                    }

                    const importPayload = {
                        tokens: tokens,
                        replace_existing: $('#replace_existing').is(':checked')
                    };

                    submitAction('import_tokens', {
                        'import_tokens_data': JSON.stringify(importPayload)
                    });
                } catch (error) {
                    alert('Error parsing file: ' + error.message);
                    // Re-enable button on failure.
                    $importBtn.prop('disabled', false).text('Import Tokens');
                }
            };

            reader.onerror = function() {
                alert('Error reading the selected file.');
                $importBtn.prop('disabled', false).text('Import Tokens');
            };

            reader.readAsText(file);
        });

        // --- EXPORT TOKENS (JSON) ---
        $('#export_tokens_btn').on('click', function(e) {
            e.preventDefault();
            const content = JSON.stringify({ tokens: customTokens.export_data }, null, 2);
            const fileName = `tokens_export_${new Date().toISOString().split('T')[0]}.json`;
            triggerDownload(content, 'data:text/json;charset=utf-8,', fileName);
        });

        // --- EXPORT TOKENS (CSV) ---
        $('#export_tokens_csv_btn').on('click', function(e) {
            e.preventDefault();
            const content = customTokens.export_csv_data;
            const fileName = `tokens_export_${new Date().toISOString().split('T')[0]}.csv`;
            triggerDownload(content, 'data:text/csv;charset=utf-8,', fileName);
        });

        // --- REMOVE ALL TOKENS ---
        $('#remove_all_tokens_btn').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to permanently delete ALL tokens? This action cannot be undone.')) {
                submitAction('remove_all_tokens', {});
            }
        });
    });

})(jQuery);