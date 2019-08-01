<style type="text/css">
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
        padding-bottom: 8px !important;
        padding-left: 0px !important;
        padding-right: 0px !important;
        width: 100%;
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
    /* fixes for last field borders END */
</style>

<form action="<?= $data['action']; ?>" method="POST" name="safechargesubmit" id="safechargesubmit">
    <?php foreach($data['html_inputs'] as $name => $value): ?>
        <input type='hidden' name='<?= $name; ?>' value='<?= $value; ?>'/>
    <?php endforeach; ?>
        
    <div id="reload_apms_warning" class="alert alert-danger hide">
        <strong><?= $$data['sc_attention']; ?></strong> <?= $$data['sc_go_to_step_2_error']; ?>
        <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
    </div>
        
    <div id="sc_pm_error" class="alert alert-danger hide">
        <span><?= $data['choose_pm_error']; ?></span>
        <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
    </div>
    
    <?php if(isset($data['upos']) && $data['upos']): ?>
        <h3 class="required"><?= $data['sc_upos_title']; ?>:</h3>
        
        <ul id="sc_upos_list" class="nav">
            <?php foreach($data['upos'] as $upo): ?>
                <li class="dropdown apm_container">
                    <div class="apm_title">
                        <?php if(isset($upo['upoData']['brand'], $data['icons'][$upo['upoData']['brand']])): ?>
                            <img src="<?= str_replace('/svg/', '/svg/solid-white/', $data['icons'][$upo['upoData']['brand']]) ?>" />
                            <?php if(isset($upo['upoData']['ccCardNumber'])): ?>
                                &nbsp;&nbsp;<span><?= $upo['upoData']['ccCardNumber']; ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <img src="<?php str_replace('/svg/', '/svg/solid-white/', $data['icons'][$upo['paymentMethodName']]) ?>" />
                        <?php endif; ?>
                        
                        <input type="radio" class="hide" name="payment_method_sc" value="<?= $upo['userPaymentOptionId'] ?>" />
                        <i class="fa fa-check hide" aria-hidden="true"></i>
                    </div>
                    
                    <?php if(in_array($upo['paymentMethodName'], array("cc_card", "dc_card"))): ?>
                        <div class="apm_fields">
                            <div class="apm_field">
                                <input id="upo_cvv_field_<?= $upo['userPaymentOptionId'] ?>" class="upo_cvv_field" name="upo_cvv_field_<?= $upo['userPaymentOptionId'] ?>" type="text" pattern="^[0-9]{3,4}$" placeholder="CVV Number">
                            </div>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <br/>
    <?php endif; ?>
        
    <?php if(isset($data['payment_methods']) && $data['payment_methods']): ?>
        <h3 class="required"><?= $data['sc_pms_title']; ?></h3>
        
        <ul id="sc_apms_list" class="nav">
            <?php foreach($data['payment_methods'] as $payment_method): ?>
                <li class="dropdown apm_container">
                    <div class="apm_title">
                        <img src="<?= str_replace('/svg/', '/svg/solid-white/', @$payment_method['logoURL']); ?>" alt="<?= @$payment_method['paymentMethodDisplayName'][0]['message'] ?>" />
                        <input type="radio" id="sc_payment_method_<?= $payment_method['paymentMethod'] ?>" class="sc_payment_method_field hide" name="payment_method_sc" value="<?= $payment_method['paymentMethod'] ?>" />
                        <i class="fa fa-check hide" aria-hidden="true"></i>
                    </div>
                    
                    <?php if(in_array($payment_method['paymentMethod'], array('cc_card', 'dc_card', 'paydotcom'))): ?>
                        <div class="apm_fields" id="sc_<?= $payment_method['paymentMethod']; ?>">
                            <div class="apm_field">
                                <div id="sc_card_number"></div>
                            </div>
                            
                            <div class="apm_field">
                                <input type="text" id="sc_card_holder_name" name="<?= $payment_method['paymentMethod']; ?>[cardHolderName]" placeholder="Card holder name" />
                            </div>
                            
                            <div class="apm_field">
                                <div id="sc_card_expiry"></div>
                            </div>
                            
                            <div class="apm_field">
                                <div id="sc_card_cvc"></div>
                            </div>
                            
                            <input type="hidden" id="<?= $payment_method['paymentMethod']; ?>_ccTempToken" name="<?= $payment_method['paymentMethod']; ?>[ccTempToken]" />
                        </div>
                    <?php elseif(count($payment_method['fields']) > 0): ?>
                        <div class="apm_fields">
                            <?php foreach($payment_method['fields'] as $p_field): ?>
                                <div class="apm_field">
                                    <input id="<?= $payment_method['paymentMethod']; ?>_<?= $p_field['name']; ?>" name="<?= $payment_method['paymentMethod']; ?>[<?= $p_field['name']; ?>]" type="<?= $p_field['type']; ?>" <?php if(isset($p_field['regex']) && !empty($p_field['regex'])): ?>pattern="<?= $p_field['regex'] ?>"<?php endif; ?> placeholder="<?= @$p_field['caption'][0]['message']; ?>" />

                                    <?php if(isset($p_field['regex']) && !empty($p_field['regex'])): ?>
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
    <?php elseif($data['payment_api'] == "rest"): ?>
        <div class="alert alert-danger hide"><?= $data['rest_no_apms_error']; ?></div>
    <?php endif;?> 
        
    <input type="hidden" name="lst" id="sc_lst" value="<?= $data['sessionToken']; ?>" />
        
    <div class="buttons">
        <div class="pull-right">
            <?php if($data['payment_api'] == "cashier"): ?>
                <button type="submit" class="btn btn-primary"><?= $data['button_confirm']; ?></button>
            <?php else: ?>
                <button id="sc_validate_submit_btn" type="button" class="btn btn-primary" onclick="scValidateAPMFields()"><?= $data['button_confirm']; ?></button>
            <?php endif; ?>
        </div>
    </div>
</form>

<script type="text/javascript">
    paymentAPI = "<?= @$data['payment_api']; ?>";
    payloadURL = "<?= @$data['payload_url']; ?>";
    
    var scLocale = "<?= $data['scLocale'] ?>";
    var scData = {
        merchantSiteId: "<?= $data['merchantSiteId']; ?>"
        ,sessionToken: "<?= $data['sessionToken'] ?>"
    };
    
    <?php if(@$data['sc_test_env'] == 'yes'): ?>
        scData.env = 'test';
    <?php endif; ?>
    
    var scTestEnv           = "<?= @$data['sc_test_env']; ?>";
    var scBtnConfirmText    = "<?= @$data['button_confirm']; ?>";
    var scBtnLoadingText    = "<?= @$data['sc_btn_loading']; ?>";
    var scTokenError        = "<?= @$data['sc_token_error']; ?>";
    var scTokenError2       = "<?= @$data['sc_token_error_2']; ?>";
    
    // for the fields
    var sfc                 = null;
    var sfcFirstField       = null;
    
    
</script>

<script type="text/javascript" src="catalog/view/javascript/sc.js"></script>
