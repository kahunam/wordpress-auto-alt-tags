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
        
        // Add some visual feedback when hovering over buttons
        $('.button').on('mouseenter', function() {
            $(this).css('opacity', '0.9');
        }).on('mouseleave', function() {
            $(this).css('opacity', '1');
        });
    });

})(jQuery);
