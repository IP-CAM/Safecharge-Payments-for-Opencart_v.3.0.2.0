<style type="text/css">
    /* 
        Created on : Oct 25, 2018, 3:23:39 PM
        Author     : SafeCharge
    */
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

    #sc_apms_list {
        margin-top: 15px;
        box-shadow: 0 2px 4px 0 rgba(0,0,0,0.19);
    }

    #sc_apms_list .apm_container {
        width: 100%;
        height: 100%;
        cursor: pointer;
        padding: 1rem 0 0 0;
        background-color: #FFFFFF;
    }

    #sc_apms_list .apm_title {
        cursor: pointer;
        border-bottom: .1rem solid #939393;
        padding-left: 0.7em;
        padding-bottom: 0.5em;
        position: relative;
    }

    #sc_apms_list .apm_title .fa-check {
        cursor: pointer;
        color: #55a985;
        font-size: 16px;
        bottom: 15px;
        position: absolute;
        right: 10px;
        top: auto;
    }

    #sc_apms_list .fa-question-circle-o {
        top: 16px;
        position: absolute;
        right: 10px;
        font-size: 16px;
        color: #14B5F1;
    }

    #sc_apms_list .apm_fields {
        display: none;
        background-color: #fafafa;
        border-bottom: .1rem solid #9B9B9B;
    }

    #sc_apms_list .apm_field {
        padding-left: 0.7em;
        padding-right: 0.7em;
        padding-top: 1em;
        position: relative;
    }

    #sc_apms_list input {
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

    /* Chrome, Firefox, Opera, Safari 10.1+ */
    #sc_apms_list .apm_field input::placeholder {
        color: #9B9B9B !important;
        opacity: 1; /* Firefox */
        font-size: 15px;
    }

    /* Internet Explorer 10-11 */
    #sc_apms_list .apm_field input:-ms-input-placeholder {
        color: #9B9B9B !important;
        font-size: 15px;
    }

    /* Microsoft Edge */
    #sc_apms_list .apm_field input::-ms-input-placeholder {
        color: #9B9B9B !important;
        font-size: 15px;
    }

    #sc_apms_list .apm_error {
        background: none;
        width: 100%;
        margin-top: 0.2rem;
        padding-top: 5px;
    }

    #sc_apms_list .apm_error label {
        color: #E7463B;
        font-size: 12px;
        text-align: left;
        font-weight: normal;
    }

    #sc_apms_list .apm_error.error_info label {
        color: #9B9B9B;
        font-style: italic;
    }

    /* fixes for last field borders */
    #sc_apms_list .apm_fields .apm_field:last-child input {
        border: 0 !important;
    }

    #sc_apms_list .apm_fields .apm_field:last-child .apm_error {
        border-top: .1rem solid #9B9B9B !important;
        margin-top: 0px;
        padding-top: 15px;
        margin-top: 2px;
    }
    /* fixes for last field borders END */
</style>

<script type="text/javascript" src="catalog/view/javascript/sc.js"></script>
<script type="text/javascript" src="https://cdn.safecharge.com/js/v1/safecharge.js"></script>

<form action="<?= $data['action']; ?>" method="POST" name="safechargesubmit" id="safechargesubmit">
    <?php foreach($data['html_inputs'] as $name => $value): ?>
        <input type='hidden' name='<?= $name; ?>' value='<?= $value; ?>'/>
    <?php endforeach; ?>
    
    <?php if(isset($data['payment_methods']) && count($data['payment_methods']) > 0): ?>
        <div id="reload_apms_warning" class="alert alert-danger hide">
            <strong>Attention!</strong> You must confirm all steps starting from Step 2, to get correct APMs!
            <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
        </div>
        
        <h3 class="required"><?= $data['sc_pms_title']; ?></h3>
        <div id="sc_pm_error" class="alert alert-danger hide">
            <span><?= $data['choose_pm_error']; ?></span>
            <a href="javascript:void(0);" class="close" onclick="$(this).parent('div').addClass('hide');" aria-label="close" title="close">&times;</a>
        </div>
        
        <ul id="sc_apms_list" class="nav">
            <?php foreach($data['payment_methods'] as $payment_method): ?>
                <li class="dropdown apm_container">
                    <div class="apm_title">
                        <img src="<?= str_replace('/svg/', '/svg/solid-white/', @$payment_method['logoURL']); ?>" alt="<?= @$payment_method['paymentMethodDisplayName'][0]['message'] ?>" />
                        <input type="radio" id="sc_payment_method_<?= $payment_method['paymentMethod'] ?>" class="sc_payment_method_field hide" name="payment_method_sc" value="<?= $payment_method['paymentMethod'] ?>" />
                        <i class="fa fa-check hide" aria-hidden="true"></i>
                    </div>
                    
                    <?php if(count($payment_method['fields']) > 0): ?>
                        <div class="apm_fields">
                            <?php foreach($payment_method['fields'] as $p_field): ?>
                                <div class="apm_field">
                                    <input id="<?= $payment_method['paymentMethod']; ?>_<?= $p_field['name']; ?>" name="<?= $payment_method['paymentMethod']; ?>[<?= $p_field['name']; ?>]" type="<?= $p_field['type']; ?>" <?php if(isset($p_field['regex']) && !empty($p_field['regex'])): ?>pattern="<?= $p_field['regex'] ?>"<?php endif; ?> placeholder="<?= @$p_field['caption'][0]['message']; ?>" />

                                    <?php if(isset($p_field['regex']) && !empty($p_field['regex'])): ?>
                                        <i class="fa fa-question-circle-o" onclick="showErrorLikeInfo(<?= 'sc_' . $p_field['name']; ?>)" aria-hidden="true"></i>
                                        <div class="apm_error" id="error_sc_<?= $p_field['name']; ?>">
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
        
    <div class="buttons">
        <div class="pull-right">
            <?php if($data['payment_api'] == "cashier"): ?>
                <input id="sc_submit_btn" type="submit" value="<?= $data['button_confirm']; ?>" class="btn btn-primary" />
            <?php else: ?>
                <input id="sc_validate_submit_btn" type="button" value="<?= $data['button_confirm']; ?>" class="btn btn-primary" onclick="scValidateAPMFields()" />
            <?php endif; ?>
        </div>
    </div>
</form>

<script type="text/javascript">
    paymentAPI = "<?= @$data['payment_api']; ?>";
    payloadURL = "<?= @$data['payload_url']; ?>";
    
    var scTestEnv           = "<?= @$data['sc_test_env']; ?>";
    var scBtnConfirmText    = "<?= @$data['button_confirm']; ?>";
    var scBtnLoadingText    = "<?= @$data['sc_btn_loading']; ?>";
    var scTokenError        = "<?= @$data['sc_token_error']; ?>";
    var scTokenError2       = "<?= @$data['sc_token_error_2']; ?>";
</script>