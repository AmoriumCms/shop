<?php namespace AmoriumCms\Shop\Controllers;

use Flash;
use BackendAuth;
use ApplicationException;
use ForbiddenException;
use AmoriumCms\Shop\Classes\BackendListPage;
use AmoriumCms\Shop\Models\Order;
use AmoriumCms\Shipping\Classes\ShipmentService;
use AmoriumCms\Shipping\Models\Shipment;

/**
 * Orders lists the orders, including the drafts where payment was not made. Status changes and payment
 * confirmations are done in the Vue page. The shipping column opens a shipment popup.
 */
class Orders extends BackendListPage
{
    public $requiredPermissions = ['shop.orders.view', 'shop.orders.manage'];

    protected $section = 'orders';

    protected $title = 'Orders';

    /**
     * onOpenShipment opens the shipment popup for the chosen carrier
     */
    public function onOpenShipment()
    {
        $this->requireShipmentAccess();
        $order = $this->shipmentOrder();
        $view = ShipmentService::show($order);

        return $this->makePartial('$/amoriumcms/shipping/views/popup.php', [
            'order' => $order,
            'view' => $view,
            'notice' => null,
            'carrier' => $view['shipment']
                ? ['code' => $view['shipment']['carrier_code'], 'name' => $view['shipment']['carrier_name']]
                : ShipmentService::carrier((string) post('carrier_code'), $order),
        ]);
    }

    /**
     * onCreateShipment
     */
    public function onCreateShipment()
    {
        $this->requireShipmentAccess();
        $order = $this->shipmentOrder();
        $desi = str_replace(',', '.', trim((string) post('desi')));
        try {
            ShipmentService::create($order, (string) post('carrier_code'), (int) post('package_count'), $desi, BackendAuth::getUser()?->id);
            $notice = ['type' => 'success', 'message' => __('The shipment has been created.')];
        }
        catch (ForbiddenException $exception) {
            throw $exception;
        }
        catch (\Throwable $exception) {
            $notice = ['type' => 'danger', 'message' => $exception->getMessage()];
        }

        return $this->shipmentRefresh($order, $notice);
    }

    /**
     * onFetchBarcode
     */
    public function onFetchBarcode()
    {
        $this->requireShipmentAccess();
        $shipment = $this->shipmentRecord();
        ShipmentService::barcode($shipment);
        Flash::success(__('The barcode has been received.'));

        return $this->shipmentRefresh($shipment->order);
    }

    /**
     * onPrintLabel returns the label HTML for a new window
     */
    public function onPrintLabel()
    {
        $this->requireShipmentAccess();

        return ['label' => ShipmentService::label($this->shipmentRecord())];
    }

    /**
     * onCancelShipment
     */
    public function onCancelShipment()
    {
        $this->requireShipmentAccess();
        $shipment = $this->shipmentRecord();
        $order = $shipment->order;
        ShipmentService::cancel($shipment);
        Flash::success(__('The shipment has been cancelled.'));

        return $this->shipmentRefresh($order);
    }

    /**
     * shipmentRefresh replaces the popup and the list cell
     */
    protected function shipmentRefresh(Order $order, ?array $notice = null): array
    {
        $order = $order->fresh();
        $view = ShipmentService::show($order);
        if (!$view['shipment']) {
            $view['package_count'] = max(1, (int) post('package_count', $view['package_count']));
            $postedDesi = trim((string) post('desi'));
            if ($postedDesi !== '') {
                $view['desi'] = $postedDesi;
            }
        }
        $carrier = $view['shipment']
            ? ['code' => $view['shipment']['carrier_code'], 'name' => $view['shipment']['carrier_name']]
            : ShipmentService::carrier((string) post('carrier_code'), $order);

        return [
            '#shipping-popup' => $this->makePartial('$/amoriumcms/shipping/views/popup.php', [
                'order' => $order,
                'view' => $view,
                'notice' => $notice,
                'carrier' => $carrier,
            ]),
            '#shipping-order-' . $order->id => $this->makePartial('$/amoriumcms/shipping/views/column.php', [
                'record' => $order,
            ]),
        ];
    }

    /**
     * shipmentOrder
     */
    protected function shipmentOrder(): Order
    {
        $order = Order::isPlaced()->find(post('order_id'));
        if (!$order) {
            throw new ApplicationException(__('The order was not found.'));
        }

        return $order;
    }

    /**
     * shipmentRecord
     */
    protected function shipmentRecord(): Shipment
    {
        $shipment = Shipment::with('order')->find(post('shipment_id'));
        if (!$shipment || !$shipment->order) {
            throw new ApplicationException(__('No open shipment was found.'));
        }

        return $shipment;
    }

    /**
     * requireShipmentAccess
     */
    protected function requireShipmentAccess(): void
    {
        if (!BackendAuth::userHasAccess('shop.orders.manage')) {
            throw new ForbiddenException;
        }
    }
}
