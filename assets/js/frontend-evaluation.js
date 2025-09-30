/**
 * Frontend Evaluation Module - Operaton DMN Evaluator
 * AJAX evaluation, result processing, and UI feedback handling
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugFrontend('Evaluation', 'Frontend evaluation module loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('Evaluation', 'ERROR: Core module not loaded!');
  throw new Error('Operaton DMN: Core module must be loaded before Evaluation module');
}

if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.utils) {
  operatonDebugMinimal('Evaluation', 'ERROR: Utils module not loaded!');
  throw new Error('Operaton DMN: Utils module must be loaded before Evaluation module');
}

if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.fields) {
  operatonDebugMinimal('Evaluation', 'ERROR: Fields module not loaded!');
  throw new Error('Operaton DMN: Fields module must be loaded before Evaluation module');
}

// =============================================================================
// MAIN EVALUATION HANDLER
// =============================================================================

window.handleEvaluateClick = function ($button) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.error('jQuery not available for handleEvaluateClick');
    window.showError('System error: jQuery not available. Please refresh the page.');
    return;
  }

  const formId = $button.data('form-id');
  const configId = $button.data('config-id');

  const lockKey = `eval_${formId}_${configId}`;
  if (window.operatonProcessingLock[lockKey]) {
    console.log('🔒 Duplicate evaluation blocked for form:', formId);
    return;
  }

  window.operatonProcessingLock[lockKey] = true;

  console.log('Button clicked for form:', formId, 'config:', configId);

  const config = window.getFormConfigCached(formId);
  if (!config) {
    console.error('Configuration not found for form:', formId);
    window.showError('Configuration error. Please contact the administrator.');
    return;
  }

  const fieldMappings = config.field_mappings;

  window.operatonButtonManager.storeOriginalText($button, formId);

  window.forceSyncRadioButtons(formId);

  setTimeout(() => {
    continueEvaluation();
  }, 100);

  function continueEvaluation() {
    if (!window.validateForm(formId)) {
      window.showError('Please fill in all required fields before evaluation.');
      return;
    }

    const formData = {};
    let hasRequiredData = true;
    const missingFields = [];

    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const fieldId = mapping.field_id;
      console.log('Processing variable:', dmnVariable, 'Field ID:', fieldId);

      let value = window.getGravityFieldValueOptimized(formId, fieldId);
      console.log('Found raw value for field', fieldId + ':', value);

      if (
        dmnVariable.toLowerCase().includes('datum') ||
        dmnVariable.toLowerCase().includes('date') ||
        ['dagVanAanvraag', 'geboortedatumAanvrager', 'geboortedatumPartner'].includes(dmnVariable)
      ) {
        if (value !== null && value !== '' && value !== undefined) {
          value = window.convertDateFormat(value, dmnVariable);
        }
      }

      console.log('Processed value for', dmnVariable + ':', value);
      formData[dmnVariable] = value;
    });

    const isAlleenstaand = formData['aanvragerAlleenstaand'];
    console.log('User is single (alleenstaand):', isAlleenstaand);

    if (isAlleenstaand === 'true' || isAlleenstaand === true) {
      console.log('User is single, setting geboortedatumPartner to null');
      formData['geboortedatumPartner'] = null;
    }

    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const value = formData[dmnVariable];

      if (isAlleenstaand === 'true' || isAlleenstaand === true) {
        if (dmnVariable === 'geboortedatumPartner') {
          return;
        }
      }

      if (value === null || value === '' || value === undefined) {
        hasRequiredData = false;
        missingFields.push(`${dmnVariable} (field ID: ${mapping.field_id})`);
      } else {
        if (!window.validateFieldType(value, mapping.type)) {
          window.showError(`Invalid data type for field ${dmnVariable}. Expected: ${mapping.type}`);
          return false;
        }
      }
    });

    if (!hasRequiredData) {
      window.showError(`Please fill in all required fields: ${missingFields.join(', ')}`);
      return;
    }

    window.operatonButtonManager.setEvaluatingState($button, formId);

    if (typeof window.operaton_ajax === 'undefined') {
      console.error('operaton_ajax not available');
      window.showError('System error: AJAX configuration not loaded. Please refresh the page.');
      window.operatonButtonManager.restoreOriginalState($button, formId);
      return;
    }

    console.log('Making AJAX call to:', window.operaton_ajax.url);

    $.ajax({
      url: window.operaton_ajax.url,
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        config_id: configId,
        form_data: formData,
      }),
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.operaton_ajax.nonce);
      },
      success: function (response) {
        console.log('AJAX success:', response);

        if (response.success && response.results) {
          console.log('Results received:', response.results);

          window.operatonPopulatingResults = true;
          console.log('🛡️ SAFEGUARD: Result population started - blocking change handlers');

          let populatedCount = 0;
          const resultSummary = [];

          Object.entries(response.results).forEach(([dmnResultField, resultData]) => {
            const resultValue = resultData.value;
            const fieldId = resultData.field_id;

            console.log('Processing result:', dmnResultField, 'Value:', resultValue, 'Field ID:', fieldId);

            let $resultField = null;

            if (fieldId) {
              $resultField = window.findFieldOnCurrentPageOptimized(formId, fieldId);
            } else {
              $resultField = window.findResultFieldOnCurrentPageOptimized(formId);
            }

            if ($resultField && $resultField.length > 0) {
              let displayValue = resultValue;

              // Convert date format if the value looks like a date
              if (typeof resultValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(resultValue)) {
                const dateFormat = window.getFieldDateFormat(formId, fieldId);

                if (dateFormat) {
                  displayValue = window.convertToGravityDateFormat(resultValue, dateFormat);
                  console.log(`✓ Converted date from ${resultValue} to ${displayValue} using format ${dateFormat}`);
                } else {
                  // Fallback to default dd/mm/yyyy if format not found
                  displayValue = window.convertToGravityDateFormat(resultValue, 'dmy');
                  console.log(`✓ Converted date from ${resultValue} to ${displayValue} using default dmy format`);
                }
              }

              $resultField.val(displayValue);
              $resultField.trigger('change');
              $resultField.trigger('input');

              populatedCount++;
              resultSummary.push(`${dmnResultField}: ${displayValue}`);

              window.highlightField($resultField);
              console.log('Populated field', fieldId, 'with result:', displayValue);
            } else {
              console.warn('No field found for result:', dmnResultField, 'Field ID:', fieldId);
            }
          });

          setTimeout(() => {
            window.operatonPopulatingResults = false;
            console.log('🛡️ SAFEGUARD: Result population completed - change handlers re-enabled');
          }, 200);

          if (response.process_instance_id) {
            window.storeProcessInstanceId(formId, response.process_instance_id);
            console.log('Stored process instance ID:', response.process_instance_id);
          }

          if (populatedCount > 0) {
            let message = `Results populated (${populatedCount}): ${resultSummary.join(', ')}`;

            if (response.process_instance_id && config.show_decision_flow) {
              message += '\n\nComplete the form to see the detailed decision flow summary on the final page.';

              if (typeof window.OperatonDecisionFlow !== 'undefined') {
                window.OperatonDecisionFlow.clearCache();
              }
            }

            window.showSuccessNotification(message);
          } else {
            window.showError('No result fields found on this page to populate.');
          }

          const currentPage = window.getCurrentPageCached(formId);
          const evalData = {
            results: response.results,
            page: currentPage,
            timestamp: Date.now(),
            formData: formData,
            processInstanceId: response.process_instance_id || null,
          };

          if (typeof Storage !== 'undefined') {
            sessionStorage.setItem(`operaton_dmn_eval_data_${formId}`, JSON.stringify(evalData));
          }
        } else {
          console.error('Invalid response structure:', response);
          window.showError('No results received from evaluation.');
        }
      },
      error: function (xhr, status, error) {
        console.error('AJAX Error:', error);
        console.error('XHR Status:', xhr.status);
        console.error('XHR Response:', xhr.responseText);

        let errorMessage = 'Error during evaluation. Please try again.';

        if (xhr.status === 0) {
          errorMessage = 'Connection error. Please check your internet connection and try again.';
        } else if (xhr.status === 400) {
          try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse.message) {
              errorMessage = errorResponse.message;
            }
          } catch (e) {
            errorMessage = 'Bad request. Please check your form data.';
          }
        } else if (xhr.status === 404) {
          errorMessage = 'Evaluation service not found. Please contact support.';
        } else if (xhr.status === 500) {
          errorMessage = 'Server error occurred during evaluation. Please try again.';
        }

        window.showError(errorMessage);
      },
      complete: function () {
        window.operatonButtonManager.restoreOriginalState($button, formId);

        setTimeout(() => {
          delete window.operatonProcessingLock[lockKey];
        }, 1000);
      },
    });
  }
};

// =============================================================================
// UI FEEDBACK FUNCTIONS
// =============================================================================

window.showSuccessNotification = function (message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Evaluation', 'jQuery not available for showSuccessNotification');
    alert(message);
    return;
  }

  $('.operaton-notification').remove();

  const $notification = $(`<div class="operaton-notification">${message}</div>`);
  $notification.css({
    position: 'fixed',
    top: '20px',
    right: '20px',
    background: '#4CAF50',
    color: 'white',
    padding: '15px 20px',
    'border-radius': '6px',
    'box-shadow': '0 3px 15px rgba(0,0,0,0.2)',
    'z-index': 99999,
    'font-family': 'Arial, sans-serif',
    'font-size': '14px',
    'font-weight': 'bold',
    'max-width': '400px',
    'white-space': 'pre-line',
  });

  $('body').append($notification);

  setTimeout(() => {
    $notification.fadeOut(300, function () {
      $(this).remove();
    });
  }, 6000);
};

window.highlightField = function ($field) {
  const $ = window.jQuery || window.$;
  if (!$ || !$field || $field.length === 0) {
    operatonDebugMinimal('Evaluation', 'jQuery or field not available for highlightField');
    return;
  }

  const originalBackground = $field.css('background-color');
  const originalBorder = $field.css('border');

  $field.css({
    'background-color': '#e8f5e8',
    border: '2px solid #4CAF50',
    transition: 'all 0.3s ease',
  });

  $('html, body').animate(
    {
      scrollTop: $field.offset().top - 100,
    },
    500
  );

  setTimeout(() => {
    $field.css({
      'background-color': originalBackground,
      border: originalBorder,
    });
  }, 3000);
};

// =============================================================================
// PROCESS INSTANCE MANAGEMENT
// =============================================================================

window.storeProcessInstanceId = function (formId, processInstanceId) {
  if (typeof Storage !== 'undefined') {
    sessionStorage.setItem(`operaton_process_${formId}`, processInstanceId);
  }
  window[`operaton_process_${formId}`] = processInstanceId;
  operatonDebugVerbose('Evaluation', 'Stored process instance ID for form', formId + ':', processInstanceId);
};

// =============================================================================
// MODULE COMPLETION
// =============================================================================

window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.evaluation = true;

operatonDebugFrontend('Evaluation', 'Frontend evaluation module loaded successfully');
