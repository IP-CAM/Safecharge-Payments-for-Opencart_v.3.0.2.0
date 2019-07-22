var billingCountryName  = '';
var paymentAPI          = '';
var tokAPMs             = ['cc_card', 'dc_card', 'paydotcom'];
var selectedPM          = '';
// for the fields
var sfc                 = null;
var sfcFirstField       = null;

/**
 * Function createSCFields
 * Call SafeCharge method and pass the parameters
 */
function createSCFields() {
    sfc = SafeCharge(scData);

    // prepare fields
    var fields = sfc.fields({
        locale: scLocale
    });

    // set some classes
    var elementClasses = {
        focus: 'focus',
        empty: 'empty',
        invalid: 'invalid',
    };

    // describe fields
    var cardNumber = sfcFirstField = fields.create('ccNumber', {
        classes: elementClasses
    });
    cardNumber.attach('#sc_card_number');

    var cardExpiry = fields.create('ccExpiration', {
        classes: elementClasses
    });
    cardExpiry.attach('#sc_card_expiry');

    var cardCvc = fields.create('ccCvc', {
        classes: elementClasses
    });
    cardCvc.attach('#sc_card_cvc');
}

/**
  * Function showErrorLikeInfo
  * Show error message as information about the field.
  * 
  * @param {int} elemId
  */
 function showErrorLikeInfo(elemId) {
    $('#error_'+elemId).addClass('error_info');

    if($('#error_'+elemId).hasClass('hide')) {
        $('#error_'+elemId).removeClass('hide');
    }
    else {
        $('#error_'+elemId).addClass('hide');
    }
 }
 
/**
  * Function validateScAPMsModal
  * When click save on modal, check for mandatory fields and validate them.
  */
function scValidateAPMFields() {
    console.log('scValidateAPMFields()')
    
    // show Loading... button
    $('#sc_validate_submit_btn')
        .prop('disabled', true)
        .val(scBtnLoadingText);
    
    var formValid = true;
    
    if(typeof selectedPM != 'undefined' && selectedPM != '') {
        // use cards
        if(selectedPM == 'cc_card' || selectedPM == 'dc_card' || selectedPM == 'paydotcom') {
            sfc.getToken(sfcFirstField).then(function(result) {
                if (result.status !== 'SUCCESS') {
                    $('#sc_validate_submit_btn')
                        .prop('disabled', false)
                        .val(scBtnConfirmText);

                    try {
                        if(result.reason) {
                            alert(result.reason);
                        }
                        else if(result.error.message) {
                            alert(result.error.message);
                        }
                    }
                    catch (exception) {
                        console.log(exception);
                        alert("Unexpected error, please try again later!");
                    }
                }
                else {
                    jQuery('#' + selectedPM + '_ccTempToken').val(result.ccTempToken);
                    jQuery('#sc_lst').val(result.sessionToken);
                    $('form#safechargesubmit').submit();
                }
            });
        }
        // use APM data
        else if(isNaN(parseInt(selectedPM))) {
            var checkId = 'sc_payment_method_' + selectedPM;

            // iterate over payment fields
            $('#' + checkId).closest('li.apm_container').find('.apm_fields input').each(function(){
                var apmField = $(this);

                if (
                    typeof apmField.attr('pattern') != 'undefined'
                    && apmField.attr('pattern') !== false
                    && apmField.attr('pattern') != ''
                ) {
                    var regex = new RegExp(apmField.attr('pattern'), "i");

                    // SHOW error
                    if(apmField.val() == '' || regex.test(apmField.val()) == false) {
                        apmField.parent('.apm_field').find('.apm_error')
                            .removeClass('error_info hide');

                        formValid = false;
                    }
                    else {
                        apmField.parent('.apm_field').find('.apm_error')
                            .addClass('hide');
                    }
                }
                else if(apmField.val() == '') {
                    formValid = false;
                }
            });

            if(!formValid) {
                scFormFalse();
                return;
            }

            $('form#safechargesubmit').submit();
        }
        // use UPO data
        else {
            if(
                $('#upo_cvv_field_' + selectedPM).length > 0
                && $('#upo_cvv_field_' + selectedPM).val() == ''
            ) {
                scFormFalse();
                return;
            }

            jQuery('#safechargesubmit').submit();
        }
    }
    else {
        scFormFalse();
        return;
    }
}

function scFormFalse() {
    $('#sc_pm_error').removeClass('hide');
    window.location.hash = 'sc_pm_error';
    window.location.hash;
    
    $('#sc_validate_submit_btn')
        .prop('disabled', false)
        .val(scBtnConfirmText);
}

$(function() {
    createSCFields();
    
    // when click on APM payment method
    $('body').on('click', 'form#safechargesubmit .apm_title', function() {
        var self = $(this);
        // check current radio
        var currRadio = self.find('input');
        currRadio.prop('checked', true);
        
        selectedPM = currRadio.val();
        
        // clear all upo cvv fields
        $('#safechargesubmit .upo_cvv_field').val('');
        
        // hide all check marks 
        $('#safechargesubmit').find('.apm_title i').addClass('hide');
        
        // mark current payment method
        self.find('i').removeClass('hide');
        
        // hide all apm_fields
        $('#safechargesubmit .apm_fields').fadeOut("slow")
            .promise()
            .done(function(){
                // show current apm_fields
                self.parent('.apm_container').find('.apm_fields').toggle('slow');
            });
    });
    
    // when change Payment Country and not click on Continue button show warning!
    if(paymentAPI == 'rest') {
        $('#collapse-payment-address').on('change', '#input-payment-country', function(){
            $('#reload_apms_warning').removeClass('hide');
        });
    }
    
});
// document ready function END
