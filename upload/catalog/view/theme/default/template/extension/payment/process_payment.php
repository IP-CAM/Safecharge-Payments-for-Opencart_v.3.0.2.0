<?= $data['header']; ?>

        <script type="text/javascript">
            $('#cart').hide();
        </script>

        <div class="container">
            <div class="alert alert-info">
                <h3>
                    <i class="fa fa-circle-o-notch fa-spin" style="font-size: 21px;"></i>&nbsp;<?= $data['process_payment']; ?>
                </h3>
            </div>

            <?php if(@$data['acsUrl'] && @$data['paRequest'] && @$data['TermUrl']): ?>
                <form action="<?= $data['acsUrl']; ?>" method="post" id="sc_payment_form">
                    <input type="hidden" name="PaReq" value="<?= $data['paRequest']; ?>">
                    <input type="hidden" name="TermUrl" value="<?= $data['TermUrl']; ?>">

                    <noscript>
                        <button type="submit">Submit</button>
                    </noscript>
                </form>

                <script type="text/javascript">
                    $(function(){
                        $("#sc_payment_form").submit();
                    });
                </script>
            <?php elseif(@$data['redirectURL'] && @$data['pendingURL']): ?>
                <noscript>
                    <a href="<?= $data['redirectURL']; ?>">Click here to redirect.</a>
                </noscript>
                
                <script type="text/javascript">
                    window.location.href = "<?= $data['redirectURL']; ?>";
                </script>
            <?php else: ?>
                <noscript>
                    <a href="<?= $data['success_url']; ?>">Click here to continue.</a>
                </noscript>
                
                <script type="text/javascript">
                    window.location.href = "<?= $data['success_url']; ?>";
                </script>
            <?php endif; ?>
        </div>
    </body>
</html>
