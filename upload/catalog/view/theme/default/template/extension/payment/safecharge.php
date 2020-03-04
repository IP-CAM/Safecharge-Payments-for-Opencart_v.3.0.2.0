<style type="text/css">
	/* vietnamese */
	@font-face {
		font-family: 'Nunito Sans';
		font-style: normal;
		font-weight: 400;
		font-display: swap;
		src: local('Nunito Sans Regular'), local('NunitoSans-Regular'), url(https://fonts.gstatic.com/s/nunitosans/v5/pe0qMImSLYBIv1o4X1M8cceyI9tScg.woff2) format('woff2');
		unicode-range: U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+1EA0-1EF9, U+20AB;
	}
	/* latin-ext */
	@font-face {
		font-family: 'Nunito Sans';
		font-style: normal;
		font-weight: 400;
		font-display: swap;
		src: local('Nunito Sans Regular'), local('NunitoSans-Regular'), url(https://fonts.gstatic.com/s/nunitosans/v5/pe0qMImSLYBIv1o4X1M8ccezI9tScg.woff2) format('woff2');
		unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
	}
	/* latin */
	@font-face {
		font-family: 'Nunito Sans';
		font-style: normal;
		font-weight: 400;
		font-display: swap;
		src: local('Nunito Sans Regular'), local('NunitoSans-Regular'), url(https://fonts.gstatic.com/s/nunitosans/v5/pe0qMImSLYBIv1o4X1M8cce9I9s.woff2) format('woff2');
		unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
	}
	
    #safechargesubmit h3.required {
        margin-top: 0px;
    }

    #safechargesubmit h3.required:before {
        content: '* ';
        color: #F00;
        font-weight: bold;
    }

    #safechargesubmit #sc_pm_error {
        color: red;
        font-size: 12px;
    }

	#sc_apms * {
		font-family: 'arial' !important;
		font-size: 13px;
	}
	
    #sc_apms_list, #sc_upos_list {
        margin-top: 15px;
        box-shadow: 0 2px 4px 0 rgba(0,0,0,0.19);
    }

    #safechargesubmit .apm_container {
        width: 100%;
        height: 100%;
        cursor: pointer;
        padding: 1rem 0 0 0;
        background-color: #FFFFFF;
    }

    #safechargesubmit .apm_title  {
        cursor: pointer;
        border-bottom: .1rem solid #939393;
        padding-left: 0.7em;
        padding-bottom: 0.5em;
        position: relative;
    }
    
    #sc_card_number, #sc_card_expiry {
        border-bottom: .1rem solid #939393;
        padding-left: 0;
        padding-bottom: 0.5em;
        line-height: inherit;
    }
    
    #sc_card_number, #sc_card_expiry, #sc_card_cvc
    {
        line-height: inherit;
        margin-top: 3px;
    }
    
    #safechargesubmit .apm_title .fa-check {
        cursor: pointer;
        color: #55a985;
        font-size: 16px;
        bottom: 15px;
        position: absolute;
        right: 10px;
        top: auto;
    }

    #safechargesubmit .fa-question-circle-o {
        top: 16px;
        position: absolute;
        right: 10px;
        font-size: 16px;
        color: #14B5F1;
    }

    #safechargesubmit .apm_fields {
        display: none;
        background-color: #fafafa;
        border-bottom: .1rem solid #9B9B9B;
    }

    #safechargesubmit .apm_field  {
        padding-left: 0.7em;
        padding-right: 0.7em;
        padding-top: 1em;
        position: relative;
    }

    #safechargesubmit input {
        border-radius: unset;
        border: 0 !important;
        background-color: inherit !important;
        border-bottom: .1rem solid #9B9B9B !important;
        border-radius: 0px !important;
        padding-left: 0px !important;
        padding-right: 0px !important;
        width: 100%;
    }
	
	#safechargesubmit input::placeholder {
		color: grey;
	}

    #safechargesubmit .apm_error {
        background: none;
        width: 100%;
        margin-top: 0.2rem;
        padding-top: 5px;
    }

    #safechargesubmit .apm_error label {
        color: #E7463B;
        font-size: 12px;
        text-align: left;
        font-weight: normal;
    }

    #safechargesubmit .apm_error.error_info label {
        color: #9B9B9B;
        font-style: italic;
    }

    /* fixes for last field borders */
    #safechargesubmit .apm_fields .apm_field:last-child input, #sc_card_cvc  {
        border: 0 !important;
    }

    #safechargesubmit .apm_fields .apm_field:last-child .apm_error {
        border-top: .1rem solid #9B9B9B !important;
        margin-top: 0px;
        padding-top: 15px;
        margin-top: 2px;
    }
    
    .SfcField iframe {
        min-height: 20px !important;
    }
	
	.sfcModal-dialog {
		width: 50%;
		margin: 0 auto;
		margin-top: 10%;
	}
    /* fixes for last field borders END */
</style>

<form action="<?= $data['action']; ?>" method="POST" name="safechargesubmit" id="safechargesubmit">
    <div id="reload_apms_warning" class="alert alert-danger hide">
        <strong><?= $data['sc_attention']; ?></strong> <?= $data['sc_go_to_step_2_error']; ?>
        <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
    </div>
        
    <div id="sc_pm_error" class="alert alert-danger hide">
        <span><?= $data['choose_pm_error']; ?></span>
        <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
    </div>
    
    <?php if(isset($data['payment_methods']) && $data['payment_methods']): ?>
        <h3 class="required"><?= $data['sc_pms_title']; ?></h3>
        
        <ul id="sc_apms_list" class="nav">
            <?php foreach($data['payment_methods'] as $payment_method): ?>
                <li class="dropdown apm_container">
                    <div class="apm_title">
                        <?php if('cc_card' == $payment_method['paymentMethod']): ?>
							<img src="catalog/view/theme/default/image/visa_mc_maestro.svg" alt="<?= @$payment_method['paymentMethodDisplayName'][0]['message'] ?>" style="height: 36px;" />
						<?php else: ?>
							<img src="<?= str_replace('/svg/', '/svg/solid-white/', @$payment_method['logoURL']); ?>" alt="<?= @$payment_method['paymentMethodDisplayName'][0]['message'] ?>" />
						<?php endif; ?>
						
                        <input type="radio" id="sc_payment_method_<?= $payment_method['paymentMethod'] ?>" class="sc_payment_method_field hide" name="payment_method_sc" value="<?= $payment_method['paymentMethod'] ?>" />
                        <i class="fa fa-check hide" aria-hidden="true"></i>
                    </div>
                    
                    <?php if(in_array($payment_method['paymentMethod'], array('cc_card', 'dc_card'))): ?>
                        <div class="apm_fields" id="sc_<?= $payment_method['paymentMethod']; ?>">
                            <div class="apm_field">
                                <input type="text" 
									   id="sc_card_holder_name" 
									   name="<?= $payment_method['paymentMethod']; ?>[cardHolderName]" 
									   placeholder="Card holder name" />
                            </div>
                            
                            <div class="apm_field">
                                <div id="card-field-placeholder"></div>
                            </div>
                        </div>
                    <?php elseif(count($payment_method['fields']) > 0): ?>
                        <div class="apm_fields">
                            <?php foreach($payment_method['fields'] as $p_field): ?>
                                <div class="apm_field">
                                    <input id="<?= $payment_method['paymentMethod']; ?>_<?= $p_field['name']; ?>" 
										   name="<?= $payment_method['paymentMethod']; ?>[<?= $p_field['name']; ?>]" 
										   type="<?= $p_field['type']; ?>" 
										   <?php if(!empty($p_field['regex'])): ?>pattern="<?= $p_field['regex'] ?>"<?php endif; ?> 
										   <?php if(!empty($p_field['caption'][0]['message'])): ?>placeholder="<?= @$p_field['caption'][0]['message']; ?>"<?php else: ?>placeholder="<?= @$p_field['name']; ?>"<?php endif; ?> />

                                    <?php if(!empty($p_field['regex']) && !empty($p_field['validationmessage'][0]['message'])): ?>
                                        <i class="fa fa-question-circle-o" onclick="showErrorLikeInfo('sc_<?= $p_field['name']; ?>')" aria-hidden="true"></i>
                                        <div class="apm_error hide" id="error_sc_<?= $p_field['name']; ?>">
                                            <label><?= $p_field['validationmessage'][0]['message']; ?></label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="alert alert-danger hide"><?= $data['rest_no_apms_error']; ?></div>
    <?php endif;?> 
        
	<input type="hidden" name="lst" id="sc_lst" value="<?= @$data['sessionToken']; ?>" />
	<input type="hidden" name="sc_transaction_id" id="sc_transaction_id" value="" />
        
    <div class="buttons">
        <div class="pull-right">
			<button id="sc_validate_submit_btn" type="button" class="btn btn-primary" onclick="scValidateAPMFields()"><?= $data['button_confirm']; ?></button>
        </div>
    </div>
</form>

<script type="text/javascript">
    var scData = {
        merchantSiteId		: "<?= $data['merchantSiteId']; ?>",
        merchantId			: "<?= $data['merchantId']; ?>",
        sessionToken		: "<?= @$data['sessionToken']; ?>",
        sourceApplication	: "<?= @$data['sourceApplication']; ?>"
    };
    
    <?php if(@$data['sc_test_env'] == 'yes'): ?>
        scData.env = 'test';
    <?php endif; ?>
    
	var scCard		= null;
	var sfc			= null;
	var selectedPM	= '';

	/**
	 * Function createSCFields
	 * Call SafeCharge method and pass the parameters
	 */
	function createSCFields() {
		sfc = SafeCharge(scData);

		// prepare fields
		var fields = sfc.fields({
			locale: "<?= $data['scLocale'] ?>",
			fonts : [{
				cssUrl: 'https://fonts.googleapis.com/css?family=Nunito+Sans:500&display=swap'
			}]
		});

		// set some classes
		var elementClasses = {
			focus: 'focus'
			,empty: 'empty'
			,invalid: 'invalid'
		};
		
		scCard = fields.create('card', {
            iconStyle: 'solid',
            style: {
                base: {
                    iconColor: "#c4f0ff",
                    color: "#000",
                    fontWeight: 500,
                    fontFamily: "arial",
                    fontSize: '12px',
                    fontSmoothing: "antialiased",
                    ":-webkit-autofill": {
                        color: "#fce883"
                    },
                    "::placeholder": {
                        color: "grey" 
                    }
                },
                invalid: {
                    iconColor: "#FFC7EE",
                    color: "#FFC7EE"
                }
            },
            classes: elementClasses
        });

        scCard.attach('#card-field-placeholder');
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
		// show Loading... button
		$('#sc_validate_submit_btn')
			.prop('disabled', true)
			.val("<?= @$data['sc_btn_loading']; ?>");

		var formValid = true;

		if(typeof selectedPM != 'undefined' && selectedPM != '') {
			// create payment with WebSDK
			if(selectedPM == 'cc_card' || selectedPM == 'dc_card') {
                sfc.createPayment({
                    sessionToken    : "<?= @$data['sessionToken'] ?>",
                    merchantId      : "<?= @$data['merchantId'] ?>",
                    merchantSiteId  : "<?= @$data['merchantSiteId'] ?>",
                    currency        : "<?= @$data['currency'] ?>",
                    amount          : "<?= @$data['amount'] ?>",
                    cardHolderName  : document.getElementById('sc_card_holder_name').value,
                    paymentOption   : scCard,
					webMasterId		: "<?= @$data['webMasterId'] ?>"
                }, function(resp){
                    console.log(resp);

                    if(typeof resp.result != 'undefined') {
                        if(resp.result == 'APPROVED' && resp.transactionId != 'undefined') {
							$('#sc_transaction_id').val(resp.transactionId);
                            $('form#safechargesubmit').submit();
                            return;
                        }
                        else if(resp.result == 'DECLINED') {
                            alert("<?= $data['sc_order_declined']; ?>");
							
							scOpenNewOrder();
                        }
                        else {
                            if(resp.errorDescription != 'undefined' && resp.errorDescription !== '') {
                                alert(resp.errorDescription);
                            }
                            else if('undefined' != resp.reason && '' !== resp.reason) {
                                alert(resp.reason);
                            }
                            else {
                                alert("<?= $data['sc_order_error']; ?>");
                            }
							
							$('#sc_validate_submit_btn')
								.prop('disabled', false)
								.val("<?= @$data['button_confirm']; ?>");
                        }
                    }
                    else {
                        alert("<?= $data['sc_order_error']; ?>");
                        console.error('Error with SDK response: ' + resp);
						
						$('#sc_validate_submit_btn')
							.prop('disabled', false)
							.val("<?= @$data['button_confirm']; ?>");
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
		}
		else {
			scFormFalse();
			return;
		}
	}
	
	function scOpenNewOrder() {
		$.ajax({
			url: 'index.php?route=<?= $data['ctr_path']; ?>',
			type: 'post',
			dataType: 'json'
		})
		.done(function(resp) {
			if('success' == resp.status) {
				// clean old data
				scData.sessionToken = resp.sessionToken;
				scCard = null;
				$('#card-field-placeholder').html('');
				
				createSCFields();
				
				$('#sc_validate_submit_btn')
					.prop('disabled', false)
					.val("<?= @$data['button_confirm']; ?>");
			}
			else {
				window.location.reload();
			}
		});
	}

	function scFormFalse() {
		$('#sc_pm_error').removeClass('hide');
		window.location.hash = 'sc_pm_error';
		window.location.hash;

		$('#sc_validate_submit_btn')
			.prop('disabled', false)
			.val("<?= @$data['button_confirm']; ?>");
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
		$('#collapse-payment-address').on('change', '#input-payment-country', function(){
			$('#reload_apms_warning').removeClass('hide');
		});

	});
	// document ready function END
</script>
