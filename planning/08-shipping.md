# MODULE 8 — Shipping & Logistics
**Priority:** 🟠 P1 | **Status:** ⬜ Belum | **Sprint:** 8

---

## Yang Perlu Dibangun
- ⬜ Ongkir calculation (RajaOngkir/Biteship API)
- ⬜ Available courier list berdasarkan origin-destination
- ⬜ EDD (Estimated Delivery Date) saat checkout
- ⬜ AWB/Resi input oleh merchant setelah kirim
- ⬜ Package tracking real-time via API kurir
- ⬜ Shipment status sync (webhook dari kurir atau polling)

---

## Entities
| Tabel | Kolom Utama |
|---|---|
| `shipments` | `order_id`, `courier` (jne/jnt/sicepat), `service` (REG/YES/OKE), `tracking_number`, `status` (enum), `estimated_delivery`, `shipped_at`, `delivered_at` |
| `shipment_trackings` | `shipment_id`, `status`, `description`, `location`, `event_time` |
| `shipping_rates` | `origin_city_id`, `destination_city_id`, `courier`, `service`, `rate_cents`, `etd_days`, `cached_at` |

---

## Enums
```php
enum ShipmentStatus: string {
    case Pending   = 'pending';
    case PickedUp  = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Returned  = 'returned';
    case Failed    = 'failed';
}
```

---

## Interface Contract
```php
interface ShippingProviderInterface {
    public function calculateCost(array $params): array;
    // params: origin_city_id, destination_city_id, weight_gram, courier
    public function getAvailableCouriers(): array;
    public function trackShipment(string $awb, string $courier): array;
    public function getProvinces(): array;
    public function getCities(int $provinceId): array;
}
```

---

## Routes
```
GET  /api/shipping/provinces                   [public]
GET  /api/shipping/cities                      [public] ?province_id=
POST /api/shipping/calculate                   [auth]
GET  /api/shipping/couriers                    [public]
GET  /api/shipments/{trackingNumber}           [public]
GET  /api/shipments/{id}/tracking              [auth]
POST /api/merchant/orders/{id}/shipment        [auth:merchant] (input AWB)
```

---

## Files to Create
```
app/Services/Shipping/ShippingService.php
app/Services/Shipping/ShippingProviderInterface.php
app/Services/Shipping/Providers/RajaOngkirService.php
app/Services/Shipping/Providers/BiteshipService.php
app/Http/Controllers/Api/Shipping/ShippingController.php
app/Http/Requests/Shipping/CalculateShippingRequest.php
app/Http/Resources/Shipping/ShipmentResource.php
app/Http/Resources/Shipping/TrackingResource.php
app/Models/Shipment.php
app/Models/ShipmentTracking.php
app/Enums/ShipmentStatus.php
database/migrations/xxxx_create_shipments_table.php
database/migrations/xxxx_create_shipment_trackings_table.php
routes/api/shipping.php
tests/Feature/Api/Shipping/ShippingTest.php
```

---

## Shared Services Needed
| Service | Kegunaan |
|---|---|
| `CacheService` | Cache ongkir hasil kalkulasi (TTL 3600s), cache provinces/cities |
| `NotificationService` | Notif saat status pengiriman berubah |

---

## Business Logic Notes
- Ongkir hasil kalkulasi di-cache per `origin+destination+weight+courier` (TTL 1 jam)
- Provinces & cities dari RajaOngkir di-cache permanen (invalidasi manual saat data berubah)
- Tracking: polling via Job yang di-schedule setiap 2 jam untuk shipment aktif
- Setelah `delivered`: otomatis update order status ke `delivered` via event
- SHIPPING_PROVIDER env: `rajaongkir` atau `biteship` — bound di AppServiceProvider
