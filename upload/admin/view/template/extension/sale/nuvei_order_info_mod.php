<script type="text/javascript">
	// try set nuvei variables
	var nuveiRefundAmountError = '<?= $nuvei_refund_amount_error; ?>';
	
	// twig file, use double {
	if(nuveiRefundAmountError.search('=') > 0) {
		var nuveiRefundAmountError		= "{{ nuvei_refund_amount_error }}";
		var nuveiAjaxUrl				= '{{ nuveiAjaxUrl }}';
		var nuveiUnexpectedError		= '{{ nuvei_unexpected_error }}';
		var nuveiOrderConfirmDelRefund	= '{{ nuvei_order_confirm_del_refund }}';
		var nuveiCreateRefund			= '{{ nuvei_create_refund }}';
		var nuveiOrderConfirmRefund		= '{{ nuvei_order_confirm_refund }}';
		var nuveiOrderId				= '{{ order_id }}';
		var nuveiBtnManualRefund		= '{{ nuvei_btn_manual_refund }}';
		var nuveiBtnRefund				= '{{ nuvei_btn_refund }}';
		var nuveiBtnVoid				= '{{ nuvei_btn_void }}';
		var nuveiOrderConfirmCancel		= '{{ nuvei_order_confirm_cancel }}';
		var nuveiBtnSettle              = '{{ nuvei_btn_settle }}';
		var nuveiOrderConfirmSettle     = '{{ nuvei_order_confirm_settle }}';
		var nuveiMoreActions            = '{{ nuvei_more_actions }}';
        var nuveiAllowRefundBtn         = '{{ nuveiAllowRefundBtn }}';
        var nuveiAllowVoidBtn           = '{{ nuveiAllowVoidBtn }}';
        var nuveiAllowSettleBtn         = '{{ nuveiAllowSettleBtn }}';
        var nuveiRefunds                = JSON.parse('{{ nuveiRefunds }}');
        var nuveiRefundId               = '{{ nuvei_refund_id }}';
        var nuveiDate                   = '{{ nuvei_date }}';
        var nuveiRemainingTotal         = '{{ nuvei_remaining_total }}';
        var nuveiRemainingTotalCurr     = '{{ remainingTotalCurr }}';
	}
	// tpl file, use php tags
	else {
		var nuveiAjaxUrl				= '<?= $nuveiAjaxUrl; ?>';
		var nuveiUnexpectedError		= '<?= $nuvei_unexpected_error; ?>';
		var nuveiOrderConfirmDelRefund	= '<?= $nuvei_order_confirm_del_refund; ?>';
		var nuveiCreateRefund			= '<?= $nuvei_create_refund; ?>';
		var nuveiOrderConfirmRefund		= '<?= $nuvei_order_confirm_refund; ?>';
		var nuveiOrderId				= '<?= $order_id; ?>';
		var nuveiBtnManualRefund		= '<?= $nuvei_btn_manual_refund; ?>';
		var nuveiBtnRefund				= '<?= $nuvei_btn_refund; ?>';
		var nuveiBtnVoid				= '<?= $nuvei_btn_void; ?>';
        var nuveiOrderConfirmCancel		= '<?= $nuvei_order_confirm_cancel; ?>';
        var nuveiBtnSettle              = '<?= $nuvei_btn_settle; ?>';
        var nuveiOrderConfirmSettle     = '<?= $nuvei_order_confirm_settle; ?>';
        var nuveiMoreActions            = '<?= $nuvei_more_actions; ?>';
        var nuveiAllowRefundBtn         = '<?= $nuveiAllowRefundBtn; ?>';
        var nuveiAllowVoidBtn           = '<?= $nuveiAllowVoidBtn; ?>';
        var nuveiAllowSettleBtn         = '<?= $nuveiAllowSettleBtn; ?>';
        var nuveiRefunds                = JSON.parse('<?= $nuveiRefunds; ?>');
        var nuveiRefundId               = '<?= $nuvei_refund_id; ?>';
        var nuveiDate                   = '<?= $nuvei_date; ?>';
        var nuveiRemainingTotal         = '<?= $nuvei_remaining_total; ?>';
        var nuveiRemainingTotalCurr     = '<?= $remainingTotalCurr; ?>';
	}
	// try set nuvei variables END

    console.log('typeof nuveiRefunds', typeof nuveiRefunds);

	// Nuvei JS
	function scOrderActions(confirmQusetion, action, orderId) {
		console.log(action);

		if('refund' == action) {
			var refAm	= $('#refund_amount').val().replace(',', '.');
			var reg		= new RegExp(/^\d+(\.\d{1,2})?$/); // match integers and decimals

			if(action == 'refund' || action == 'refundManual') { 
				if(!reg.test(refAm) || isNaN(refAm) || refAm <= 0) {
					alert(nuveiRefundAmountError);
					return;
				}
			}
		}

		if(confirm(confirmQusetion + ' #' + orderId + '?')) {
			var spinnerId = (action == 'void' ? 'void' : 'refund') + '_spinner';
			$('#' + spinnerId).removeClass('hide');
			
			// disable sc custom buttons
			$('.sc_order_btns').each(function(){
				$(this).attr('disabled', true);
			});
			
			console.log('before ajax');
			
			$.ajax({
				url: nuveiAjaxUrl,
				type: 'post',
				dataType: 'json',
				data: {
					orderId: orderId
					,action: action
					,amount: $('#refund_amount').val()
				}
			})
			.done(function(resp) {
				console.log('done', resp)
		
				if(resp.hasOwnProperty('status')) {
					if(resp.status == 1) {
						window.location.href = window.location.toString().replace('/info', '');
						return;
					}
					
					if(resp.status == 0) {
						if(resp.hasOwnProperty('msg')) {
							alert(resp.msg);
						}
						else {
							alert(nuveiUnexpectedError);
						}
						

						$('#' + spinnerId).addClass('hide');
						
						// enable sc custom buttons
						$('.sc_order_btns').each(function(){
							$(this).attr('disabled', false);
						});
						
						return;
					}
				}
				else {
					alert(nuveiUnexpectedError);
				}
			})
			.fail(function(resp) {
				console.error('ajax response error:', resp);
			});
		}
	}

	function deleteManualRefund(id, amount) {
		if(confirm(nuveiOrderConfirmDelRefund)) {
			$('#sc_refund_' + id).find('.fa-circle-o-notch').removeClass('hide');
			$('#sc_refund_' + id).find('button').addClass('hide');

			$.ajax({
				url: nuveiAjaxUrl,
				type: 'post',
				dataType: 'json',
				data: {
					refId: id
					,action: 'deleteManualRefund'
					,amount: amount
				}
			})
			.done(function(resp) {
				if(resp.success) {
					$('#sc_refund_' + id).remove();

					var totRefParts = $('#sc_total_refund').html().split('-');
					var newTotRef = parseFloat(totRefParts[1]) - amount;

					$('#sc_total_refund').html(totRefParts[0] + '-' + newTotRef.toFixed(2));
				}
				else {
					$('#sc_refund_' + id).find('.fa-circle-o-notch').addClass('hide');
					$('#sc_refund_' + id).find('button').removeClass('hide');

					alert(resp.msg);
				}
			});
		}
	}

	$(function(){
		// 1.set the changes in Options table
		var scPlaceOne = $('#content .container-fluid .row .col-md-4:nth-child(3)').find('table tbody');

		if(scPlaceOne.length > 0) {
			// 1.1.place Refund button
			if('1' == nuveiAllowRefundBtn) {
				var scRefundBtnsHtml = 
					'<tr class="sc_rows">'
						+ '<td class="text-left" colspan="3">'
							+ '<span>'+ nuveiCreateRefund +'&nbsp;&nbsp;<i id="refund_spinner" class="fa fa-circle-o-notch fa-spin hide"></i></span>'
							+ '<div class="input-group pull-right" style="max-width:70%;">'
								+ '<input type="text" class="form-control" style="height: 22px; padding: 2px 5px; padding: 2px 5px; line-height: 1.5; border-radius: 3px;" id="refund_amount" value="" />'
								+ '<span class="input-group-btn">'
										+ '<button id="sc_manual_refund_btn" class="btn btn-danger btn-xs sc_order_btns" type="button" onclick="scOrderActions(\''+ nuveiOrderConfirmRefund +'\', \'refundManual\', '+ nuveiOrderId +')">'+ nuveiBtnManualRefund +'</button>';
						
				// add SC Refund button only when order is Complete (paid)
				scRefundBtnsHtml +=
										'<button class="btn btn-danger btn-xs sc_order_btns" type="button" onclick="scOrderActions(\''+ nuveiOrderConfirmRefund +'\', \'refund\', '+ nuveiOrderId +')">'+ nuveiBtnRefund +'</button>';
						
				scRefundBtnsHtml += 		
								'</span>'
							+ '</div>'
						+ '</td>'
					+ '</tr>';
						
				scPlaceOne.append(scRefundBtnsHtml);
			}
			// place Refund button END

			// 1.2.set Void btn
			var scVoidButton = '';

			if('1' == nuveiAllowVoidBtn) {
				scVoidButton = '<button class="btn btn-danger btn-xs sc_order_btns" onclick="scOrderActions(\''+ nuveiOrderConfirmCancel +'\', \'void\', '+ nuveiOrderId +')">'+ nuveiBtnVoid +'</button>';
			}
			// set Void btn END

			// 1.3.set Settle btn
			var scSettleButton = '';
			
			if('1' == nuveiAllowSettleBtn) {
				scSettleButton = '<button class="btn btn-success btn-xs sc_order_btns" onclick="scOrderActions(\''+ nuveiOrderConfirmSettle +'\', \'settle\','+ nuveiOrderId +')">'+ nuveiBtnSettle +'</button>';
			}
			// set Settle btn END

			// 1.4.place Settle and Void button
			scPlaceOne.append(
				'<tr class="sc_rows">'
					+ '<td class="text-left" colspan="3">'
						+ '<span>'+ nuveiMoreActions +'&nbsp;&nbsp;<i id="void_spinner" class="fa fa-circle-o-notch fa-spin hide"></i></span>'
						+ '<div class="btn-group pull-right">'
							+ scVoidButton
							+ scSettleButton
						+ '</div>'
					+ '</td>'
				+ '</tr>'
			);
			// place Settle and Void button END
		}
		// set the changes in Options table END

		// 2.add SC Refunds
		var scPlaceTwo = $('#content .container-fluid').children('div').eq(2).find('table:nth-child(2) tbody');

		if(scPlaceTwo.length > 0) {
			if(nuveiRefunds != 'undefined' && nuveiRefunds.length > 0) {
				// 2.1 collect Refunds
				var scRefundsRows = '';

                for(var i in nuveiRefunds) {
					scRefundsRows += 
						'<tr id="sc_refund_'+ nuveiRefunds[i].id +'">'
							+ '<td class="text-left">'
								+ nuveiRefunds[i].clientUniqueId;

                    if(!nuveiRefunds.hasOwnProperty('transactionId')) {
						scRefundsRows +=
								'<button type="button" class="btn btn-danger btn-xs pull-right" onclick="deleteManualRefund('+ nuveiRefunds[i].id +', '+ nuveiRefunds[i].amount +')"><i class="fa fa-trash"></i></button>'
								+ '<i class="fa fa-circle-o-notch fa-spin hide pull-right"></i>'
                    }
                    
					scRefundsRows +=
							'</td>'
							+ '<td class="text-left">'+ nuveiRefunds[i].transactionId +'</td>'
							+ '<td class="text-left">'+ nuveiRefunds[i].responseTimeStamp +'</td>'
							+ '<td class="text-right" colspan="2">'+ nuveiRefunds[i].amount_curr +'</td>'
						+ '</tr>';
                }
				// 2.1 collect Refunds END

				// 2.2.place Refunds
				scPlaceTwo.append(
					'<tr>'
						+ '<td class="text-left"><strong>'+ nuveiRefundId +'</strong></td>'
						+ '<td class="text-left"><strong>Transaction ID</strong></td>'
						+ '<td class="text-left"><strong>'+ nuveiDate +'</strong></td>'
						+ '<td class="text-right" colspan="2"><strong>Refund Amount</strong></td>'
					+ '</tr>'

					+ scRefundsRows

					+ '<tr>'
						+ '<td class="text-right" colspan="4"><strong>'+ nuveiRemainingTotal +'</strong></td>'
						+ '<td class="text-right"><strong>'+ nuveiRemainingTotalCurr +'</strong></td>'
					+ '</tr>'
				);
			}
		}
		// 2.add SC Refunds END
	});
	// Nuvei JS END
</script>