/**
 * Auto Alt Tags Admin JavaScript
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
        
        const logContent = $('#log-content');
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
        $('#progress-bar').val(percentage);
        $('#progress-percentage').text(percentage + '%');
        $('#progress-text').text(message);
    }

    /**
     * Show error messages
     */
    function showErrors(errors) {
        if (!errors || errors.length === 0) return;
        
        const logContent = $('#log-content');
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
        $('#alt-tag-progress').hide();
        $('#control-buttons').show();
        $('#stop-processing').hide();
        $('#start-processing').show();
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
        const $button = $('#test-api');
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
        const $button = $('#test-first-five');
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
            $('#proceed-with-all').show();
        } else {
            $('#proceed-with-all').hide();
        }
        
        $('#test-results-content').html(html);
        $('#test-results-modal').show();
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
        const resultSpan = $('#test-result-' + provider);
        resultSpan.html('').removeClass('success error');
        
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
        $('.provider-setting').hide();
        
        // Show selected provider setting
        $('.provider-setting[data-provider="' + selectedProvider + '"]').show();
        
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

    // Document ready
    $(document).ready(function() {
        
        // Start processing button
        $('#start-processing').on('click', function(e) {
            e.preventDefault();
            
            if (isProcessing) return;
            
            if (!confirm('This will generate alt tags for all images without them. Continue?')) {
                return;
            }
            
            isProcessing = true;
            shouldStop = false;
            processedImages = 0;
            
            debugLog('Starting alt tag processing...');
            
            $('#control-buttons').hide();
            $('#alt-tag-progress').show();
            $('#stop-processing').show();
            $('#start-processing').hide();
            updateProgress(0, 'Starting...');
            
            // Clear debug log
            $('#log-content').empty();
            
            // Get initial stats then start processing
            getInitialStats(function() {
                if (totalImages === 0) {
                    alert('No images need alt tags!');
                    resetUI();
                } else {
                    updateProgress(0, 'Processing ' + totalImages + ' images...');
                    processAltTags();
                }
            });
        });
        
        // Stop processing button
        $('#stop-processing').on('click', function(e) {
            e.preventDefault();
            shouldStop = true;
            debugLog('Stop requested by user');
            $(this).text('Stopping...');
        });
        
        // Test API button
        $('#test-api').on('click', function(e) {
            e.preventDefault();
            testAPIConnection();
        });
        
        // Test first 5 images button
        $('#test-first-five').on('click', function(e) {
            e.preventDefault();
            testFirstFiveImages();
        });
        
        // Modal close handlers
        $('#close-modal, #close-modal-btn').on('click', function() {
            $('#test-results-modal').hide();
        });
        
        // Proceed with all images button
        $('#proceed-with-all').on('click', function() {
            $('#test-results-modal').hide();
            $('#start-processing').click();
        });
        
        // Refresh stats button
        $('#refresh-stats').on('click', function(e) {
            e.preventDefault();
            refreshStats();
        });
        
        // Toggle debug log visibility when debug mode checkbox changes
        $('#auto_alt_debug_mode').on('change', function() {
            if ($(this).is(':checked')) {
                $('#debug-log').show();
            } else {
                $('#debug-log').hide();
            }
        });
        
        // Provider selection change handler
        $('#auto_alt_provider').on('change', function() {
            handleProviderChange();
        });
        
        // Initialize provider display on page load
        handleProviderChange();
        
        // Test API key buttons
        $('.test-api-key').on('click', function(e) {
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
