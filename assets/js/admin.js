/**
 * Auto Alt Tags Admin JavaScript
 * All selectors prefixed with ka_alt_ to avoid conflicts
 *
 * @package AutoAltTags
 * @since 1.1.0
 */

(function($) {
    'use strict';

    let isProcessing = false;
    let shouldStop = false;
    let totalImages = 0;
    let processedImages = 0;

    /**
     * Log debug message to console and UI
     */
    function debugLog(message) {
        console.log('[Auto Alt Tags]', message);

        const logContent = $('#ka_alt_log_content');
        if (logContent.length) {
            const timestamp = new Date().toLocaleTimeString();
            logContent.append(`<div>${timestamp} - ${message}</div>`);
            logContent.scrollTop(logContent[0].scrollHeight);
        }
    }

    /**
     * Update progress bar
     */
    function updateProgress(percentage, message) {
        $('#ka_alt_progress_bar').val(percentage);
        $('#ka_alt_progress_percentage').text(percentage + '%');
        $('#ka_alt_progress_text').text(message);
    }

    /**
     * Show error messages
     */
    function showErrors(errors) {
        if (!errors || errors.length === 0) return;

        const logContent = $('#ka_alt_log_content');
        errors.forEach(function(error) {
            debugLog('ERROR: ' + error);
        });
    }

    /**
     * Get initial statistics to know total images
     */
    function getInitialStats(callback) {
        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_image_stats',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    totalImages = response.data.without_alt;
                    debugLog('Total images to process: ' + totalImages);
                    if (callback) callback();
                }
            }
        });
    }

    /**
     * Process alt tags batch by batch
     */
    function processAltTags() {
        if (shouldStop) {
            debugLog('Processing stopped by user');
            resetUI();
            return;
        }

        debugLog('Sending request to process batch...');

        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'process_alt_tags',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                debugLog('Response received: ' + JSON.stringify(response));

                if (response.success) {
                    // Update processed count based on the message
                    // Extract processed count from message like "Processed 10/50 images"
                    const match = response.data.message.match(/Processed (\d+)\/(\d+) images/);
                    if (match) {
                        processedImages = parseInt(match[1]);
                        totalImages = parseInt(match[2]);
                    }

                    // Calculate real progress
                    const realProgress = totalImages > 0 ? (processedImages / totalImages * 100) : 0;
                    updateProgress(realProgress.toFixed(1), response.data.message);

                    if (response.data.errors && response.data.errors.length > 0) {
                        showErrors(response.data.errors);
                    }

                    if (response.data.completed || shouldStop) {
                        debugLog('Processing completed');
                        updateProgress(100, 'Processing complete!');
                        setTimeout(function() {
                            resetUI();
                            refreshStats();
                        }, 2000);
                    } else {
                        // Continue processing next batch
                        // Add a small delay to see the progress
                        setTimeout(processAltTags, 500);
                    }
                } else {
                    debugLog('Error: ' + response.data);
                    alert('Error: ' + response.data);
                    resetUI();
                }
            },
            error: function(xhr, status, error) {
                debugLog('AJAX error: ' + status + ' - ' + error);
                debugLog('Response: ' + xhr.responseText);
                alert('Error processing images: ' + error);
                resetUI();
            }
        });
    }

    /**
     * Reset UI after processing
     */
    function resetUI() {
        isProcessing = false;
        shouldStop = false;
        processedImages = 0;
        totalImages = 0;
        $('#ka_alt_progress').hide();
        $('#ka_alt_control_buttons').show();
        $('#ka_alt_stop_processing').hide();
        $('#ka_alt_start_processing').show();
        $('#ka_alt_resume_processing').hide();
        $('#ka_alt_missing_only_notice').hide();
    }

    /**
     * Refresh statistics
     */
    function refreshStats() {
        debugLog('Refreshing statistics...');

        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_image_stats',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    debugLog('Statistics updated');
                    // Reload page to show updated stats
                    location.reload();
                }
            },
            error: function(xhr, status, error) {
                debugLog('Failed to refresh stats: ' + error);
            }
        });
    }

    /**
     * Test API connection
     */
    function testAPIConnection() {
        debugLog('Testing API connection...');

        // Show loading state on button
        const $button = $('#ka_alt_test_api');
        const originalText = $button.text();
        $button.text('Testing...').prop('disabled', true);

        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_api_connection',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    debugLog('API test successful: ' + response.data.response);
                    alert(response.data.message + '\n\nAPI Response: ' + response.data.response);
                } else {
                    debugLog('API test failed: ' + response.data);
                    alert('API Test Failed: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                debugLog('API test error: ' + status + ' - ' + error);
                alert('Connection test failed: ' + error);
            },
            complete: function() {
                // Restore button state
                $button.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Test first 5 images
     */
    function testFirstFiveImages() {
        debugLog('Testing first 5 images...');

        // Show loading state on button
        const $button = $('#ka_alt_test_first_five');
        const originalText = $button.text();
        $button.text('Testing...').prop('disabled', true);

        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_first_five',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    debugLog('Test completed successfully');
                    displayTestResults(response.data);
                } else {
                    debugLog('Test failed: ' + response.data);
                    alert('Test Failed: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                debugLog('Test error: ' + status + ' - ' + error);
                alert('Test failed: ' + error);
            },
            complete: function() {
                // Restore button state
                $button.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Display test results in modal
     */
    function displayTestResults(data) {
        let html = '<div style="margin-bottom: 20px;">';
        html += '<p><strong>' + data.message + '</strong></p>';
        html += '<p>Provider: <strong>' + data.provider + '</strong> | Model: <strong>' + data.model + '</strong></p>';
        html += '</div>';

        if (data.results && data.results.length > 0) {
            html += '<div style="display: grid; gap: 15px;">';

            data.results.forEach(function(result) {
                html += '<div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; display: flex; gap: 15px;">';

                // Thumbnail
                if (result.thumbnail) {
                    html += '<div style="flex-shrink: 0;">';
                    html += '<img src="' + result.thumbnail + '" alt="Thumbnail" style="width: 100px; height: 100px; object-fit: cover; border-radius: 3px;">';
                    html += '</div>';
                }

                // Content
                html += '<div style="flex-grow: 1;">';
                html += '<h4 style="margin: 0 0 10px 0;">' + result.title + '</h4>';

                if (result.success) {
                    html += '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
                    html += '<strong>Generated Alt Text:</strong><br>';
                    html += '"' + result.alt_text + '"';
                    html += '</div>';
                } else {
                    html += '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 3px; margin-bottom: 10px;">';
                    html += '<strong>Error:</strong> ' + result.error;
                    html += '</div>';
                }

                html += '<small><a href="' + result.url + '" target="_blank">View Full Image</a></small>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div style="margin-top: 20px;">';
            html += '<h3>Errors:</h3>';
            html += '<ul>';
            data.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }

        // Show proceed button if there were successful results
        const hasSuccess = data.results && data.results.some(r => r.success);
        if (hasSuccess) {
            $('#ka_alt_proceed_with_all').show();
        } else {
            $('#ka_alt_proceed_with_all').hide();
        }

        $('#ka_alt_test_results_content').html(html);
        $('#ka_alt_test_results_modal').show();
    }

    /**
     * Build a rate-limit table row
     */
    function rateLimitRow(label, value) {
        return '<tr>' +
            '<td style="padding:2px 14px 2px 0;color:#555;">' + label + '</td>' +
            '<td style="font-weight:bold;">' + value + '</td>' +
        '</tr>';
    }

    /**
     * Show rate limit info for the selected model/provider after a successful key test
     */
    function showRateLimitInfo(provider) {
        const $container = $('#ka_alt_rate_limit_result_' + provider);
        if (!$container.length || !autoAltAjax.providerLimits) return;

        const providerData = autoAltAjax.providerLimits[provider];
        if (!providerData) { $container.hide(); return; }

        const selectedModel = $('#auto_alt_model_name').val();
        let rows = '';
        let subtitle = '';
        let note = '';

        if (provider === 'gemini') {
            const limits = (providerData.models || {})[selectedModel];
            if (!limits) { $container.hide(); return; }
            const name = (autoAltAjax.modelNames || {})[selectedModel] || selectedModel;
            subtitle = 'Rate limits for <em>' + name + '</em> (' + providerData.tier + ')';
            rows += rateLimitRow('Requests / minute (RPM)', limits.rpm);
            rows += rateLimitRow('Requests / day (RPD)',    limits.rpd ? limits.rpd.toLocaleString() : '—');
            rows += rateLimitRow('Tokens / minute (TPM)',   limits.tpm ? limits.tpm.toLocaleString() : '—');
            rows += rateLimitRow('Safe batch size',         limits.max_batch + ' images');
            note = 'Batch processing will pause ' + limits.sleep + 's between each API call to stay within the RPM limit.';

        } else if (provider === 'openai') {
            const limits = (providerData.models || {})[selectedModel];
            if (!limits) { $container.hide(); return; }
            subtitle = 'Rate limits for <em>' + selectedModel + '</em> (' + providerData.tier + ')';
            rows += rateLimitRow('Requests / minute (RPM)', limits.rpm);
            rows += rateLimitRow('Requests / day (RPD)',    limits.rpd ? limits.rpd.toLocaleString() : '—');
            rows += rateLimitRow('Tokens / minute (TPM)',   limits.tpm ? limits.tpm.toLocaleString() : '—');
            note = 'Upgrade to higher tiers for increased limits.';

        } else if (provider === 'claude') {
            const limits = (providerData.models || {})[selectedModel];
            if (!limits) { $container.hide(); return; }
            subtitle = 'Rate limits for <em>' + selectedModel + '</em> (' + providerData.tier + ')';
            rows += rateLimitRow('Requests / minute (RPM)',       limits.rpm);
            rows += rateLimitRow('Input tokens / minute (ITPM)',  limits.itpm ? limits.itpm.toLocaleString() : '—');
            rows += rateLimitRow('Output tokens / minute (OTPM)', limits.otpm ? limits.otpm.toLocaleString() : '—');
            note = 'Limits apply per model class. Upgrade spend to raise limits.';

        } else if (provider === 'openrouter') {
            const limits = providerData.all || {};
            subtitle = 'Rate limits (' + providerData.tier + ')';
            rows += rateLimitRow('Requests / minute (RPM)', limits.rpm || '—');
            rows += rateLimitRow('Requests / day (RPD)',    limits.rpd ? limits.rpd.toLocaleString() : '—');
            note = 'Free tier limits apply across all models routed through OpenRouter.';
        }

        const docsUrl = providerData.docs || '#';
        const html =
            '<div style="background:#f0fdf4;border:1px solid #86efac;border-left:4px solid #22c55e;padding:10px 14px;border-radius:4px;font-size:13px;">' +
                '<strong style="display:block;margin-bottom:6px;">✓ API key valid &mdash; ' + subtitle + '</strong>' +
                '<table style="border-collapse:collapse;">' + rows + '</table>' +
                (note ? '<p style="margin:6px 0 0;color:#555;font-size:12px;">' + note + '</p>' : '') +
                '<p style="margin:4px 0 0;font-size:12px;">' +
                    '<a href="' + docsUrl + '" target="_blank" rel="noopener noreferrer">View full rate limit docs →</a>' +
                '</p>' +
            '</div>';

        $container.html(html).show();
    }

    /**
     * Test individual provider API key
     */
    function testProviderKey(provider, apiKey, button) {
        debugLog('Testing ' + provider + ' API key...');

        // Show loading state on button
        const originalText = button.text();
        button.text('Testing...').prop('disabled', true);

        // Clear previous results
        const resultSpan = $('#ka_alt_test_result_' + provider);
        resultSpan.html('').removeClass('success error');
        $('#ka_alt_rate_limit_result_' + provider).hide();

        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'test_provider_key',
                provider: provider,
                api_key: apiKey,
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    debugLog(provider + ' API test successful');
                    resultSpan.html('✓ Valid').addClass('success').css({
                        'color': '#46b450',
                        'font-weight': 'bold',
                        'margin-left': '10px'
                    });
                    showRateLimitInfo(provider);
                } else {
                    debugLog(provider + ' API test failed: ' + response.data);
                    resultSpan.html('✗ Invalid').addClass('error').css({
                        'color': '#dc3232',
                        'font-weight': 'bold',
                        'margin-left': '10px'
                    });
                }
            },
            error: function(xhr, status, error) {
                debugLog(provider + ' API test error: ' + status + ' - ' + error);
                resultSpan.html('✗ Error').addClass('error').css({
                    'color': '#dc3232',
                    'font-weight': 'bold',
                    'margin-left': '10px'
                });
            },
            complete: function() {
                // Restore button state
                button.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Handle provider selection change
     */
    function handleProviderChange() {
        const selectedProvider = $('#auto_alt_provider').val();

        // Hide all provider settings
        $('.ka_alt_provider_setting').hide();

        // Show selected provider setting
        $('.ka_alt_provider_setting[data-provider="' + selectedProvider + '"]').show();

        // Update model dropdown based on provider
        updateModelDropdown(selectedProvider);
    }

    /**
     * Update model dropdown based on provider
     */
    function updateModelDropdown(provider) {
        // This would need to be implemented with PHP data or AJAX
        // For now, we'll just log the change
        debugLog('Provider changed to: ' + provider);
    }

    /**
     * Start or resume batch processing
     */
    function startProcessing(skipConfirm) {
        if (isProcessing) return;

        if (!skipConfirm && !confirm('This will generate alt tags for all images without them. Continue?')) {
            return;
        }

        isProcessing = true;
        shouldStop = false;
        processedImages = 0;

        debugLog('Starting alt tag processing (missing only)...');

        $('#ka_alt_control_buttons').hide();
        $('#ka_alt_missing_only_notice').hide();
        $('#ka_alt_progress').show();
        $('#ka_alt_stop_processing').show();
        updateProgress(0, 'Starting...');

        // Clear debug log
        $('#ka_alt_log_content').empty();

        // Get initial stats then start processing
        getInitialStats(function() {
            if (totalImages === 0) {
                alert('No images need alt tags!');
                resetUI();
            } else {
                updateProgress(0, 'Processing ' + totalImages + ' images without alt text...');
                processAltTags();
            }
        });
    }

    /**
     * Check for an incomplete session from a previous run and show Resume button
     */
    function checkForSession() {
        $.ajax({
            url: autoAltAjax.ajaxurl,
            type: 'POST',
            data: { action: 'check_alt_session', nonce: autoAltAjax.nonce },
            success: function(response) {
                if (response.success && response.data.has_session) {
                    const d = response.data;
                    $('#ka_alt_resume_processing')
                        .text('Resume Processing (' + d.remaining + ' of ' + d.session_total + ' remaining)')
                        .show();
                    $('#ka_alt_missing_only_notice')
                        .html('<strong>Previous session detected.</strong> ' + d.processed + ' images were already tagged. ' +
                              'Click <em>Resume Processing</em> to continue, or <em>Start Auto-Tagging All Images</em> to restart.')
                        .show();
                }
            }
        });
    }

    // Document ready
    $(document).ready(function() {

        // Check for resumable session on page load
        checkForSession();

        // Start processing button
        $('#ka_alt_start_processing').on('click', function(e) {
            e.preventDefault();
            startProcessing(false);
        });

        // Resume processing button (no confirm dialog)
        $('#ka_alt_resume_processing').on('click', function(e) {
            e.preventDefault();
            startProcessing(true);
        });

        // Stop processing button
        $('#ka_alt_stop_processing').on('click', function(e) {
            e.preventDefault();
            shouldStop = true;
            debugLog('Stop requested by user');
            $(this).text('Stopping...');
        });

        // Test API button
        $('#ka_alt_test_api').on('click', function(e) {
            e.preventDefault();
            testAPIConnection();
        });

        // Test first 5 images button
        $('#ka_alt_test_first_five').on('click', function(e) {
            e.preventDefault();
            testFirstFiveImages();
        });

        // Modal close handlers
        $('#ka_alt_close_modal, #ka_alt_close_modal_btn').on('click', function() {
            $('#ka_alt_test_results_modal').hide();
        });

        // Proceed with all images button
        $('#ka_alt_proceed_with_all').on('click', function() {
            $('#ka_alt_test_results_modal').hide();
            startProcessing(true);
        });

        // Refresh stats button
        $('#ka_alt_refresh_stats').on('click', function(e) {
            e.preventDefault();
            refreshStats();
        });

        // Toggle debug log visibility when debug mode checkbox changes
        $('#auto_alt_debug_mode').on('change', function() {
            if ($(this).is(':checked')) {
                $('#ka_alt_debug_log').show();
            } else {
                $('#ka_alt_debug_log').hide();
            }
        });

        // Provider selection change handler
        $('#auto_alt_provider').on('change', function() {
            handleProviderChange();
        });

        // Re-render rate limit info when model changes (if key already tested valid)
        $('#auto_alt_model_name').on('change', function() {
            const provider = $('#auto_alt_provider').val();
            if ($('#ka_alt_rate_limit_result_' + provider).is(':visible')) {
                showRateLimitInfo(provider);
            }
        });

        // Initialize provider display on page load
        handleProviderChange();

        // Test API key buttons
        $('.ka_alt_test_api_key').on('click', function(e) {
            e.preventDefault();
            const provider = $(this).data('provider');
            const apiKey = $('#auto_alt_' + provider + '_api_key').val() ||
                           $('#auto_alt_gemini_api_key').val(); // fallback for backwards compatibility

            if (!apiKey) {
                alert('Please enter an API key first');
                return;
            }

            testProviderKey(provider, apiKey, $(this));
        });

        // Add some visual feedback when hovering over buttons
        $('.button').on('mouseenter', function() {
            $(this).css('opacity', '0.9');
        }).on('mouseleave', function() {
            $(this).css('opacity', '1');
        });
    });

})(jQuery);
