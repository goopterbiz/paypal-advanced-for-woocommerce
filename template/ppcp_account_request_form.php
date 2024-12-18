<script type="text/javascript">
    
    window.addEventListener('message', function (e) {
                    const data = e.data;
                    const decoded = JSON.parse(data);
                    if (typeof decoded.message_type !== 'undefined' && decoded.message_type === 'ppcp_contact_form') {
                        setTimeout(
                            function() {
                                $('.ppcp_account_request-Modal-overlay').hide();
                                $('.ppcp_account_request-Modal').hide();
                            }, 5000);
                    }
                });
</script>
<div class="ppcp_account_request-Modal" style="display: none;">
    <div class="ppcp_account_request-Modal-header">
        <div>
            <button class="ppcp_account_request-Modal-return ppcp_account_request-icon-chevron-left">
                <?php esc_html_e( 'Return', 'paypal-advanced-for-woocommerce' ); ?>
            </button>
            <h2>
                <?php esc_html_e( 'PPCP Account Request', 'paypal-advanced-for-woocommerce' ); ?>
            </h2>
        </div>
        <button class="ppcp_account_request-Modal-close ppcp_account_request-icon-close">
            <?php esc_html_e( 'Close', 'paypal-advanced-for-woocommerce' ); ?>
        </button>
    </div>
    <div class="ppcp_account_request-Modal-content">
        <div class="ppcp_account_request-Modal-question ppcp_account_request-isOpen">
            <iframe src="<?php echo $ppcp_account_request_form_url; ?>" width="650px" height="420px;" marginheight="0" frameborder="0" border="0" scrolling="auto"></iframe>
        </div>
    </div>
</div>
<div class="ppcp_account_request-Modal-overlay"></div>
