<div id="shop-list" class="shop-list">
    <?= $this->listRender() ?>
</div>
<script>
    if (location.hash.replace(/^#\/?/, '')) {
        document.getElementById('shop-list').hidden = true;
    }
</script>
<?= $this->makePartial('app') ?>
<script>
    document.addEventListener('change', function (event) {
        var select = event.target.closest('[data-shipping-carrier]');
        if (!select || !select.value || !window.jQuery) {
            return;
        }
        jQuery.popup({
            handler: 'onOpenShipment',
            extraData: {
                order_id: select.getAttribute('data-order-id'),
                carrier_code: select.value
            }
        });
        select.value = '';
    });
</script>
