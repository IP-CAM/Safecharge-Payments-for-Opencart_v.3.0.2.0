var billingCountryName = '';
var paymentAPI = '';
var tokAPMs = ['cc_card', 'paydotcom'];
var tokAPMsFields = {
    cardNumber: 'ccCardNumber'
    ,expirationMonth: 'ccExpMonth'
    ,expirationYear: 'ccExpYear'
    ,cardHolderName: 'ccNameOnCard'
    ,CVV: ''
};
var selectedPM = '';

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
    
    var formValid = true;
     
    if(selectedPM != '') {
        $('#sc_pm_error').addClass('hide');
        
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
                if(regex.test(apmField.val()) == false || apmField.val() == '') {
                    apmField.parent('.apm_field').find('.apm_error')
                        .removeClass('error_info hide');

                    formValid = false;
                }
                // HIDE error
                else {
                    apmField.parent('.apm_field').find('.error').addClass('hide');
                }
            }
        });
    }
    else {
        formValid = false;
    }
    
    if(!formValid) {
        $('#sc_pm_error').removeClass('hide');
        window.location.hash = 'sc_pm_error';
        window.location.hash;
        return;
    }
    
    if(tokAPMs.indexOf(selectedPM) == -1) {
        $('form#safechargesubmit').submit();
        return;
    }
    
    var payload = {
        merchantSiteId: '',
        sessionToken:   '',
        billingAddress: {
            city:       $('#safechargesubmit input[name="city"]').val(),
            country:    $('#safechargesubmit input[name="country"]').val(),
            zip:        $('#safechargesubmit input[name="zip"]').val(),
            email:      $('#safechargesubmit input[name="email"]').val(),
            firstName:  $('#safechargesubmit input[name="first_name"]').val(),
            lastName:   $('#safechargesubmit input[name="last_name"]').val()
        },
        cardData: {
            cardNumber:         $('#' + selectedPM + '_' + tokAPMsFields.cardNumber).val(),
            cardHolderName:     $('#' + selectedPM + '_' + tokAPMsFields.cardHolderName).val(),
            expirationMonth:    $('#' + selectedPM + '_' + tokAPMsFields.expirationMonth).val(),
            expirationYear:     $('#' + selectedPM + '_' + tokAPMsFields.expirationYear).val(),
            CVV:                null
        }
    };
    
    // we set environment only if its test
    if(typeof scTestEnv != 'undefined' && scTestEnv == 'yes') {
        payload.environment = 'test';
    }
    
    // show Loading... button
    $('#sc_validate_submit_btn')
        .prop('disabled', true)
        .val(scBtnLoadingText);

    // call rest api to get first 3 parameters of payload
    $.ajax({
        type: "POST",
        url: payloadURL,
        data: { needST: 1 }, // need a Session Token
        dataType: 'json'
    })
        .done(function(resp){
            if(resp.status == 1 && typeof resp.data != 'undefined') {
                payload.merchantSiteId = resp.data.merchantId;
                payload.sessionToken = resp.data.sessionToken;

                // get tokenization card number
                if(typeof Safecharge != 'undefined') {
                    Safecharge.card.createToken(payload, safechargeResultHandler);
                }
                else {
                    $('#sc_pm_error span').html(scTokenError);
                    $('#sc_pm_error').removeClass('hide');
                    console.log('Safecharge Object is undefined.');
                }
            }
            else {
                $('#sc_pm_error span').html(scTokenError);
                $('#sc_pm_error').removeClass('hide');
            }
        });
}

/**
  * Function safechargeResultHandler
  * This function is just a handler for createToken method.
  * It just replaces the card number with a token.
  * 
  * @param {object} resp
  */
function safechargeResultHandler(resp) {
    console.log('safechargeResultHandler()');
    
    if(resp.status == 'ERROR') {
        // show Confirm Order button
        $('#sc_validate_submit_btn')
            .prop('disabled', false)
            .val(scBtnConfirmText);
        
        $('#sc_pm_error span').html(scTokenError2);
        if(typeof resp.reason != 'undefined' && resp.reason != '') {
            $('#sc_pm_error span').html(resp.reason);
        }
        $('#sc_pm_error').removeClass('hide');
    }
    else if(resp.status == 'SUCCESS') {
        $('#' + selectedPM + '_' + tokAPMsFields.cardNumber).val(resp.ccTempToken);
        
        $('form#safechargesubmit')
            .append('<input type="hidden" name="lst", value="'+resp.sessionToken+'" />')
            .submit();
    }
}


$(function() {
    // when click on APM payment method
    $('body').on('click', 'form#safechargesubmit .apm_title', function() {
        // hide error under title
        $('#sc_pm_error').addClass('hide');
        
        // hide all check marks 
        $('#sc_apms_list').find('.apm_title i').addClass('hide');
        
        // hide all containers with fields
        $('#sc_apms_list').find('.apm_fields').each(function(){
            var self = $(this);
            if(self.css('display') == 'block') {
                self.slideToggle('slow');
            }
        });
        
        // mark current payment method
        $(this).find('i').removeClass('hide');
        
        // hide bottom border of apm_fields if the container is empty
        if($(this).parent('li').find('.apm_fields').html() == '') {
            $(this).parent('li').find('.apm_fields').css('border-bottom', 0);
        }
        // expand payment fields
        if($(this).parent('li').find('.apm_fields').css('display') == 'none') {
            $(this).parent('li').find('.apm_fields').slideToggle('slow');
        }
        
        // unchck SC payment methods
        $('form#safechargesubmit').find('input.sc_payment_method_field').attr('checked', false);
        
        // check current radio
        var currRadio = $(this).find('input');
        currRadio.prop('checked', true);
        
        selectedPM = currRadio.val();
        
        // hide errors
        $('.apm_error').addClass('hide');
    });
    
    // when change Payment Country and not click on Continue button show warning!
    if(paymentAPI == 'rest') {
        $('#collapse-payment-address').on('change', '#input-payment-country', function(){
            $('#reload_apms_warning').removeClass('hide');
        });
    }
    
});
// document ready function END
