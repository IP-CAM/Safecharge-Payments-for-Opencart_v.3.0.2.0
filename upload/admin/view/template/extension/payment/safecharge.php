<?= $data['header'] . $data['column_left']; ?>

<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <a onclick="$('#form').submit();" class="btn btn-primary" style="cursor:pointer"><i class="fa fa-save"></i></a>
                
                <a class="btn btn-default" title="" data-toggle="tooltip" href="<?= @$cancel; ?>" data-original-title="Cancel">
                    <i class="fa fa-reply"></i>
                </a>
            </div>
            
            <h1><?= $data['heading_title']; ?></h1>
            
            <ul class="breadcrumb">
                <?php foreach ($data['breadcrumbs'] as $breadcrumb): ?>
                    <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    
    <div class="container-fluid">
        <?php if ($data['error_permission']): ?>
            <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= $data['error_permission']; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-pencil"></i> <?= $data['text_edit']; ?></h3>
        </div>
        
        <div class="panel-body">
            <?php if(@$data['error_warning']): ?>
                <div class="alert alert-danger alert-dismissible">
                    <i class="fa fa-exclamation-circle"></i> <?= $data['error_warning']; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if(@$data['success']): ?>
                <div class="alert alert-success alert-dismissible">
                    <i class="fa fa-check-circle"></i> <?= $data['success']; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            
            <form action="<?= $data['action']; ?>" method="post" enctype="multipart/form-data" id="form" class="form-horizontal">
                <div class="form-group required">
                    <label class="col-sm-2 control-label"><?= $data['entry_ppp_Merchant_ID']; ?></label>

                    <div class="col-sm-10">
                        <input type="text" name="<?= $settigs_prefix; ?>ppp_Merchant_ID" value="<?= @$data[$settigs_prefix . 'ppp_Merchant_ID']; ?>" class="form-control" required="" />

                        <?php if ($data['error_ppp_Merchant_ID']): ?>
                            <span class="text-danger"><?= $data['error_ppp_Merchant_ID']; ?></span>
                        <?php endif ?>
                    </div>
                </div>
                
                <div class="form-group required">
                    <label class="col-sm-2 control-label" for="input-order-status"><?= $data['entry_ppp_Merchant_Site_ID']; ?></label>
                    
                    <div class="col-sm-10">
                        <input class="form-control" type="text" name="<?= $settigs_prefix; ?>ppp_Merchant_Site_ID" value="<?= $data[$settigs_prefix . 'ppp_Merchant_Site_ID']; ?>" required="" />
                        <?php if ($data['error_ppp_Merchant_Site_ID']): ?>
                            <span class="text-danger"><?= $data['error_ppp_Merchant_Site_ID']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group required">
                    <label class="col-sm-2 control-label"><?= $data['entry_secret']; ?></label>
                    
                    <div class="col-sm-10">
                        <input class="form-control" type="text" name="<?= $settigs_prefix; ?>secret" value="<?= $data[$settigs_prefix . 'secret']; ?>" required="" />
                        <?php if ($data['error_secret']): ?>
                            <span class="text-danger"><?= $data['error_secret']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_hash_type']; ?></label>
                    
                    <div class="col-sm-10">
                        <select class="form-control" name="<?= $settigs_prefix; ?>hash_type">
                            <option value="sha256" <?php if (@$data[$settigs_prefix . 'hash_type'] == "sha256"): ?>selected="selected"<?php endif; ?>>sha256</option>
                            <option value="md5" <?php if (@$data[$settigs_prefix . 'hash_type'] == "md5"): ?>selected="selected"<?php endif; ?>>md5</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_test_mode']; ?></label>
                    
                    <div class="col-sm-10">
                        <select class="form-control" name="<?= $settigs_prefix; ?>test_mode">
                            <option value="yes" <?php if (@$data[$settigs_prefix . 'test_mode'] == 'yes'): ?>selected="selected"<?php endif; ?>><?= $data['entry_yes']; ?></option>
                            <option value="no" <?php if (@$data[$settigs_prefix . 'test_mode'] == 'no'): ?>selected="selected"<?php endif; ?>><?= $data['entry_no']; ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_force_http']; ?></label>
                    
                    <div class="col-sm-10">
                        <select class="form-control" name="<?= $settigs_prefix; ?>force_http">
                            <option value="yes" <?php if (@$data[$settigs_prefix . 'force_http'] == 'yes'): ?>selected="selected"<?php endif; ?>><?= $data['entry_yes']; ?></option>
                            <option value="no" <?php if (@$data[$settigs_prefix . 'force_http'] == 'no'): ?>selected="selected"<?php endif; ?>><?= $data['entry_no']; ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_create_logs']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>create_logs" class="form-control">
                            <option value="yes" <?php if(@$data[$settigs_prefix . 'create_logs'] == "yes"): ?>selected="selected"<?php endif; ?>><?= $data['entry_yes']; ?></option>
                            <option value="no" <?php if(@$data[$settigs_prefix . 'create_logs'] == "no"): ?>selected="selected"<?php endif; ?>><?= $data['entry_no']; ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_total']; ?></label>

                    <div class="col-sm-10">
                        <input type="text" name="<?= $settigs_prefix; ?>total" value="<?= @$data[$settigs_prefix . 'total']; ?>" class="form-control"/>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_order_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>order_status_id" class="form-control">
                            <?php foreach($data['order_statuses'] as $order_status): ?>
                                <option value="<?= $order_status['order_status_id'] ?>" <?php if($order_status['order_status_id'] == $data[$settigs_prefix . 'order_status_id']): ?>selected="selected"<?php endif; ?>>
                                    <?= $order_status['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_pending_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>pending_status_id" class="form-control">
                            <?php foreach($data['order_statuses'] as $order_status): ?>
                                <option value="<?= $order_status['order_status_id']; ?>" <?php if($order_status['order_status_id'] == $data[$settigs_prefix . 'pending_status_id']):?>selected="selected"<?php endif; ?>><?= $order_status['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_canceled_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>canceled_status_id" class="form-control">
                            <?php foreach($data['order_statuses'] as $order_status): ?>
                                <option value="<?= $order_status['order_status_id']; ?>" <?php if($order_status['order_status_id'] == $data[$settigs_prefix . 'canceled_status_id']):?>selected="selected"<?php endif; ?>><?= $order_status['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_failed_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>failed_status_id" class="form-control">
                            <?php foreach($data['order_statuses'] as $order_status): ?>
                                <option value="<?= $order_status['order_status_id']; ?>" <?php if($order_status['order_status_id'] == $data[$settigs_prefix . 'failed_status_id']):?>selected="selected"<?php endif; ?>><?= $order_status['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_chargeback_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>chargeback_status_id" class="form-control">
                            <?php foreach($data['order_statuses'] as $order_status): ?>
                                <option value="<?= $order_status['order_status_id']; ?>" <?php if($order_status['order_status_id'] == $data[$settigs_prefix . 'chargeback_status_id']):?>selected="selected"<?php endif; ?>><?= $order_status['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_geo_zone']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>geo_zone_id" class="form-control">
                            <?php foreach($data['geo_zones'] as $geo_zone): ?>
                            <option value="<?= $geo_zone['geo_zone_id']; ?>" <?php if($geo_zone['geo_zone_id'] == $data[$settigs_prefix . 'geo_zone_id']): ?>selected="selected"<?php endif; ?>>
                                <?= $geo_zone['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_status']; ?></label>

                    <div class="col-sm-10">
                        <select name="<?= $settigs_prefix; ?>status" class="form-control">
                            <option value="1" <?php if($data[$settigs_prefix . 'status'] == 1): ?>selected="selected"<?php endif; ?>><?= $data['text_enabled']; ?></option>
                            <option value="0" <?php if($data[$settigs_prefix . 'status'] == 0): ?>selected="selected"<?php endif; ?>><?= $data['text_disabled']; ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="col-sm-2 control-label"><?= $data['entry_sort_order']; ?></label>

                    <div class="col-sm-10">
                        <input type="text" name="<?= $settigs_prefix; ?>sort_order" value="<?= $data[$settigs_prefix . 'sort_order']; ?>" class="form-control" size="3" />
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $data['footer']; ?>