/**
 * Admin JavaScript for Auto Alt Tags Plugin
 */

(function($) {
    'use strict';

    let processing = false;
    let stopped = false;
    let processedCount = 0;
    let totalCount = 0;
    let currentBatch = 0;

    $(document).ready(function() {
        initializeAdmin();
    });

    function initializeAdmin() {
        bindEvents();
        updateButtonStates();
    }

    function bindEvents() {
        $('#start-processing').on('click', startProcessing);
        $('#stop-processing').on('click', stopProcessing);
        $('#refresh-stats').on('click', refreshStats);
        
        // Prevent form submission during processing
        $('form').on('submit', function(e) {
            if (processing) {
                e.preventDefault();
                showNotice('Please wait for processing to complete before changing settings.', 'warning');
                return false;
            }
        });

        // Auto-refresh stats periodically when not processing
        setInterval(function() {
            if (!processing) {
                refreshStats(true); // Silent refresh
            }
        }, 30000); // Every 30 seconds
    }

    function startProcessing() {
        if (processing) return;
        
        processing = true;
        stopped = false;
        processedCount = 0;
        totalCount = 0;
        currentBatch = 0;
        
        updateButtonStates();
        showProgressSection();
        clearLog();
        
        logMessage('Starting auto-tagging process...', 'info');
        
        processNextBatch();
    }

    function stopProcessing() {
        if (!processing) return;
        
        stopped = true;
        logMessage('Stopping processing after current batch...', 'info');
        updateProgressText('Stopping...');
        
        // Update button states
        $('#stop-processing').prop('disabled', true).text('Stopping...');
    }

    function processNextBatch() {
        if (stopped) {
            finishProcessing('Processing stopped by user.');
            return;
        }

        currentBatch++;
        
        $.ajax({
            url: autoAltAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'process_alt_tags',
                nonce: autoAltAjax.nonce
            },
            timeout: 60000, // 60 second timeout
            success: function(response) {
                if (response.success) {
                    handleBatchSuccess(response.data);
                } else {
                    handleBatchError(response.data || 'Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Request failed: ';
                if (status === 'timeout') {
                    errorMessage += 'Request timed out. Try reducing batch size.';
                } else {
                    errorMessage += error || 'Unknown error';
                }
                handleBatchError(errorMessage);
            }
        });
    }

    function handleBatchSuccess(data) {
        // Update progress
        updateProgress(data.progress, data.message);
        
        // Log any errors from this batch
        if (data.errors && data.errors.length > 0) {
            data.errors.forEach(function(error) {
                logMessage(error, 'error');
            });
        }
        
        // Log batch completion
        logMessage(`Batch ${currentBatch} completed: ${data.message}`, 'success');
        
        if (data.completed || stopped) {
            finishProcessing(data.message);
        } else {
            // Continue with next batch after a small delay
            setTimeout(function() {
                if (!stopped) {
                    processNextBatch();
                }
            }, 1000);
        }
    }

    function handleBatchError(errorMessage) {
        logMessage('Error: ' + errorMessage, 'error');
        finishProcessing('Processing failed due to an error.');
    }

    function finishProcessing(message) {
        processing = false;
        stopped = false;
        
        updateButtonStates();
        logMessage('Processing completed: ' + message, 'info');
        
        // Show final result
        showNotice(message, 'success');
        
        // Refresh stats to show updated numbers
        setTimeout(function() {
            refreshStats();
        }, 1000);
    }

    function updateProgress(percentage, message) {
        $('.progress-fill').css('width', percentage + '%');
        $('#progress-percentage').text(Math.round(percentage) + '%');
        updateProgressText(message);
    }

    function updateProgressText(text) {
        $('#progress-text').text(text);
    }

    function showProgressSection() {
        $('#alt-tag-progress').show();
        $('#control-buttons').hide();
    }

    function hideProgressSection() {
        $('#alt-tag-progress').hide();
        $('#control-buttons').show();
    }

    function updateButtonStates() {
        if (processing) {
            $('#start-processing').prop('disabled', true);
            $('#stop-processing').prop('disabled', false);
            $('#refresh-stats').prop('disabled', true);
            
            // Add loading spinner to start button
            $('#start-processing').html('<span class="loading-spinner"></span>Processing...');
        } else {
            $('#start-processing').prop('disabled', false);
            $('#stop-processing').prop('disabled', true);
            $('#refresh-stats').prop('disabled', false);
            
            // Remove loading spinner
            $('#start-processing').html('🚀 Start Auto-Tagging Images');
            $('#stop-processing').text('⏹️ Stop Processing');
            
            hideProgressSection();
        }
    }

    function refreshStats(silent = false) {
        if (!silent) {
            $('#refresh-stats').prop('disabled', true).html('<span class="loading-spinner"></span>Refreshing...');
        }
        
        $.ajax({
            url: autoAltAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_image_stats',
                nonce: autoAltAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                    if (!silent) {
                        showNotice('Statistics refreshed successfully.', 'success', 3000);
                    }
                } else {
                    if (!silent) {
                        showNotice('Failed to refresh statistics.', 'error');
                    }
                }
            },
            error: function() {
                if (!silent) {
                    showNotice('Failed to refresh statistics.', 'error');
                }
            },
            complete: function() {
                if (!silent) {
                    $('#refresh-stats').prop('disabled', false).html('🔄 Refresh Statistics');
                }
            }
        });
    }

    function updateStatsDisplay(stats) {
        $('.stat-card').eq(0).find('h3').text(formatNumber(stats.total));
        $('.stat-card').eq(1).find('h3').text(formatNumber(stats.with_alt));
        $('.stat-card').eq(2).find('h3').text(formatNumber(stats.without_alt));
        $('.stat-card').eq(3).find('h3').text(stats.percentage + '%');
        
        // Update button state based on images needing alt tags
        if (stats.without_alt === 0) {
            $('#start-processing').prop('disabled', true).text('✅ All images have alt tags');
        } else if (!processing) {
            $('#start-processing').prop('disabled', false).html('🚀 Start Auto-Tagging Images');
        }
    }

    function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    function logMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = $(`<div class="log-entry ${type}">[${timestamp}] ${message}</div>`);
        
        $('#processing-log').append(logEntry);
        
        // Scroll to bottom
        const logContainer = $('#processing-log')[0];
        if (logContainer) {
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // Limit log entries to prevent memory issues
        const maxEntries = 100;
        const entries = $('#processing-log .log-entry');
        if (entries.length > maxEntries) {
            entries.slice(0, entries.length - maxEntries).remove();
        }
    }

    function clearLog() {
        $('#processing-log').empty();
    }

    function showNotice(message, type = 'info', autoHide = 5000) {
        // Remove existing notices
        $('.auto-alt-notice').remove();
        
        const notice = $(`
            <div class="auto-alt-notice ${type}">
                <p>${message}</p>
            </div>
        `);
        
        $('.auto-alt-tags-admin h1').after(notice);
        
        // Auto-hide notice
        if (autoHide > 0) {
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, autoHide);
        }
    }

    // Utility function to handle page visibility changes
    // Pause processing when tab is not visible to save resources
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && processing) {
            logMessage('Tab hidden - processing continues in background', 'info');
        } else if (!document.hidden && processing) {
            logMessage('Tab visible - processing resumed', 'info');
        }
    });

    // Handle beforeunload event to warn about ongoing processing
    window.addEventListener('beforeunload', function(e) {
        if (processing) {
            const message = 'Image processing is still in progress. Are you sure you want to leave?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Only when not in an input field
        if ($(e.target).is('input, textarea, select')) {
            return;
        }
        
        // Ctrl/Cmd + Enter to start processing
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            if (!processing) {
                startProcessing();
            }
        }
        
        // Escape to stop processing
        if (e.key === 'Escape' && processing) {
            e.preventDefault();
            stopProcessing();
        }
        
        // R to refresh stats
        if (e.key === 'r' && !processing) {
            e.preventDefault();
            refreshStats();
        }
    });

    // Add keyboard shortcut hints
    function addKeyboardHints() {
        const hints = `
            <div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 12px; color: #666;">
                <strong>Keyboard shortcuts:</strong>
                Ctrl+Enter: Start processing | Escape: Stop processing | R: Refresh stats
            </div>
        `;
        $('.settings-section').after(hints);
    }

    // Initialize keyboard hints
    addKeyboardHints();

})(jQuery);
