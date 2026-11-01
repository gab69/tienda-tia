<?php
namespace App\Controller;

use App\{Config, Json, Request};
use App\Repository\{SettingRepository, ProductRepository, CampaignRepository, SaleRepository, AuditLog};

final class CatalogController extends BaseController
{
    public function config() { Json::send((new SettingRepository($this->db))->config()); }

    public function products()
    {
        $page = min(100000, max(1, Request::toInt('page')));
        [$rows, $total, $more] = (new ProductRepository($this->db))->page(
            Request::text('buscar', 100), Request::toInt('campania'), $page, (int)Config::get('page_size', 40)
        );
        Json::ok(['productos' => $rows, 'total' => $total, 'page' => $page, 'more' => $more]);
    }

    public function campaigns() { Json::send((new CampaignRepository($this->db))->active()); }

    public function defaultCampaign() { Json::send(['id' => (new CampaignRepository($this->db))->defaultId()]); }

    public function registerSale()
    {
        $items = Request::get('items', []);
        if (!is_array($items) || !$items) Json::fail('El carrito está vacío');
        $saved = (new SaleRepository($this->db))->register($items);
        if ($saved === 0) Json::fail('Los productos del pedido ya no están disponibles');
        (new AuditLog($this->db))->log('Venta', 'ventas', "$saved items");
        Json::ok();
    }
}
