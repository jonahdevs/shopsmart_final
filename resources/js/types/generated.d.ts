declare namespace App {
namespace Enums {
export type AttributeType = 'select' | 'color' | 'button';
export type CategorySection = 'navbar' | 'homepage_featured' | 'footer';
export type CategoryStatus = 'draft' | 'active' | 'inactive' | 'archived';
export type CouponType = 'fixed' | 'percent';
export type OrderStatus = 'pending' | 'processing' | 'out_for_delivery' | 'completed' | 'cancelled' | 'refunded';
export type PaymentStatus = 'pending' | 'success' | 'failed' | 'cancelled' | 'refunded';
export type ProductLinkType = 'upsell' | 'cross_sell' | 'accessory' | 'spare_part';
export type ProductStatus = 'draft' | 'published' | 'scheduled' | 'archived';
export type ProductType = 'simple' | 'variable' | 'grouped' | 'bundled';
export type ProductVisibility = 'visible' | 'hidden' | 'catalog' | 'search';
export type ReviewStatus = 'pending' | 'approved' | 'rejected';
export type StockStatus = 'in_stock' | 'out_of_stock' | 'backorder';
}
}
