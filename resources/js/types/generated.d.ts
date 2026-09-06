declare namespace App {
namespace Data {
export type AccountReviewData = {
id: number,
product: App.Data.ProductCardData,
rating: number,
title: string | null,
body: string,
status: App.Enums.ReviewStatus,
statusLabel: string,
statusVariant: string,
verifiedPurchase: boolean,
submittedAt: string | null,
submittedAtForHumans: string | null,
};
export type AccountStatsData = {
orderCount: number,
addressCount: number,
wishlistCount: number,
awaitingReviewCount: number,
};
export type AddressData = {
id: number | null,
label: string | null,
firstName: string,
lastName: string,
fullName: string,
phone: string | null,
line1: string,
line2: string | null,
city: string,
county: string | null,
postalCode: string | null,
countryCode: string,
deliveryNotes: string | null,
isDefault: boolean,
summary: string,
};
export type AttributeValueData = {
id: number,
attributeId: number,
value: string,
label: string,
slug: string,
colorCode: string | null,
};
export type BrandData = {
id: number,
name: string,
slug: string,
logoUrl: string | null,
description: string | null,
};
export type BreadcrumbData = {
name: string,
slug: string | null,
};
export type CartData = {
items: App.Data.CartItemData[],
itemCount: number,
lineCount: number,
subtotalCents: number,
subtotalFormatted: string,
isEmpty: boolean,
hasPriceChanges: boolean,
};
export type CartItemData = {
key: string,
productId: number,
variantId: number | null,
name: string,
slug: string,
sku: string | null,
brandName: string | null,
optionLabel: string | null,
image: App.Data.ImageData | null,
quantity: number,
unitPriceCents: number,
unitPriceFormatted: string,
lineTotalCents: number,
lineTotalFormatted: string,
currentUnitPriceCents: number,
currentUnitPriceFormatted: string,
priceChanged: boolean,
inStock: boolean,
maxQuantity: number | null,
};
export type CatalogFilterData = {
q: string,
categories: string[],
brands: number[],
priceMin: number,
priceMax: number | null,
priceCeiling: number,
inStockOnly: boolean,
minRating: number,
tag: string,
newArrivalsOnly: boolean,
sort: string,
hasActiveFilters: boolean,
};
export type CategoryData = {
id: number,
name: string,
slug: string,
description: string | null,
iconSvg: string | null,
image: App.Data.ImageData | null,
productCount: number | null,
children: App.Data.CategoryData[],
};
export type CheckoutQuoteData = {
lines: App.Data.PricedLineData[],
totals: App.Data.OrderTotalsData,
minOrderValueCents: number,
minOrderValueFormatted: string,
meetsMinimum: boolean,
freeShippingRemainingCents: number | null,
freeShippingRemainingFormatted: string | null,
blockers: string[],
};
export type CompareAttributeData = {
name: string,
values: (string| null)[],
};
export type CompareData = {
products: App.Data.ProductCardData[],
attributes: App.Data.CompareAttributeData[],
limit: number,
};
export type FacetOptionData = {
id: number,
name: string,
slug: string,
count: number,
};
export type HeroSlideData = {
id: number,
headline: string,
subheadline: string | null,
ctaLabel: string | null,
ctaUrl: string | null,
hasCallToAction: boolean,
alignment: string,
textTheme: string,
desktopImage: App.Data.ImageData | null,
mobileImage: App.Data.ImageData | null,
};
export type ImageData = {
url: string,
webpUrl: string | null,
zoomUrl: string | null,
zoomWebpUrl: string | null,
thumbUrl: string | null,
placeholder: string | null,
alt: string,
isCover: boolean,
};
export type OrderData = {
id: number,
orderNumber: string,
status: App.Enums.OrderStatus,
statusLabel: string,
statusVariant: string,
paymentStatus: App.Enums.PaymentStatus,
paymentStatusLabel: string,
paymentStatusVariant: string,
paymentMethod: string | null,
customerName: string,
customerEmail: string,
customerPhone: string | null,
lines: App.Data.PricedLineData[],
itemCount: number,
totals: App.Data.OrderTotalsData,
shippingAddress: App.Data.AddressData | null,
customerNote: string | null,
placedAt: string,
paidAt: string | null,
awaitsPayment: boolean,
};
export type OrderTotalsData = {
subtotalCents: number,
subtotalFormatted: string,
discountCents: number,
discountFormatted: string,
shippingCents: number,
shippingFormatted: string,
taxCents: number,
taxFormatted: string,
totalCents: number,
totalFormatted: string,
pricesIncludeTax: boolean,
taxLabel: string,
couponCode: string | null,
deliveryMethod: App.Enums.DeliveryMethod,
shippingIsFree: boolean,
};
export type PricedLineData = {
productId: number | null,
variantId: number | null,
name: string,
slug: string | null,
sku: string | null,
brandName: string | null,
optionLabel: string | null,
image: App.Data.ImageData | null,
quantity: number,
unitPriceCents: number,
unitPriceFormatted: string,
subtotalCents: number,
subtotalFormatted: string,
discountCents: number,
discountFormatted: string,
taxRate: number,
taxCents: number,
taxFormatted: string,
totalCents: number,
totalFormatted: string,
};
export type ProductCardData = {
id: number,
name: string,
slug: string,
sku: string | null,
brandName: string | null,
image: App.Data.ImageData | null,
priceCents: number | null,
salePriceCents: number | null,
effectivePriceCents: number | null,
priceFormatted: string | null,
salePriceFormatted: string | null,
effectivePriceFormatted: string | null,
discountPercent: number | null,
isOnSale: boolean,
ratingAverage: number | null,
ratingCount: number,
inStock: boolean,
stockStatus: App.Enums.StockStatus,
type: App.Enums.ProductType,
isVariable: boolean,
requiresOptions: boolean,
};
export type ProductDetailData = {
id: number,
name: string,
slug: string,
sku: string | null,
modelNumber: string | null,
shortDescription: string | null,
description: string | null,
technicalSpecification: string | null,
brand: App.Data.BrandData | null,
primaryCategory: App.Data.CategoryData | null,
breadcrumbs: App.Data.BreadcrumbData[],
images: App.Data.ImageData[],
priceCents: number | null,
salePriceCents: number | null,
effectivePriceCents: number | null,
priceFormatted: string | null,
salePriceFormatted: string | null,
effectivePriceFormatted: string | null,
discountPercent: number | null,
isOnSale: boolean,
ratingAverage: number | null,
ratingCount: number,
inStock: boolean,
stockStatus: App.Enums.StockStatus,
stockQuantity: number | null,
allowBackorder: boolean,
minOrderQuantity: number,
type: App.Enums.ProductType,
isVariable: boolean,
requiresOptions: boolean,
specifications: App.Data.SpecificationData[],
variants: App.Data.ProductVariantData[],
variationAttributes: App.Data.VariationAttributeData[],
defaultVariantId: number | null,
metaTitle: string | null,
metaDescription: string | null,
};
export type ProductListData = {
data: App.Data.ProductCardData[],
currentPage: number,
lastPage: number,
perPage: number,
total: number,
hasMorePages: boolean,
};
export type ProductVariantData = {
id: number,
sku: string,
optionLabel: string,
priceCents: number | null,
salePriceCents: number | null,
effectivePriceCents: number | null,
priceFormatted: string | null,
salePriceFormatted: string | null,
effectivePriceFormatted: string | null,
discountPercent: number | null,
isOnSale: boolean,
inStock: boolean,
stockStatus: App.Enums.StockStatus,
stockQuantity: number | null,
attributeValueIds: number[],
image: App.Data.ImageData | null,
};
export type ReviewData = {
id: number,
authorName: string,
rating: number,
title: string | null,
body: string,
verifiedPurchase: boolean,
publishedAt: string | null,
publishedAtForHumans: string | null,
};
export type ShopperStateData = {
cartCount: number,
wishlistCount: number,
compareCount: number,
wishlistProductIds: number[],
compareProductIds: number[],
compareLimit: number,
};
export type SpecificationData = {
name: string,
values: string[],
};
export type VariationAttributeData = {
id: number,
name: string,
slug: string,
type: App.Enums.AttributeType,
values: App.Data.AttributeValueData[],
};
}
namespace Enums {
export type AttributeType = 'select' | 'color' | 'button';
export type CategorySection = 'navbar' | 'homepage_featured' | 'footer';
export type CategoryStatus = 'draft' | 'active' | 'inactive' | 'archived';
export type CouponType = 'fixed' | 'percent';
export type DeliveryMethod = 'delivery' | 'pickup';
export type OrderStatus = 'pending' | 'processing' | 'out_for_delivery' | 'completed' | 'cancelled' | 'refunded';
export type PaymentStatus = 'pending' | 'success' | 'failed' | 'cancelled' | 'refunded';
export type ProductLinkType = 'upsell' | 'cross_sell' | 'accessory' | 'spare_part';
export type ProductStatus = 'draft' | 'published' | 'scheduled' | 'archived';
export type ProductType = 'simple' | 'variable' | 'grouped' | 'bundled';
export type ProductVisibility = 'visible' | 'hidden' | 'catalog' | 'search';
export type ReviewStatus = 'pending' | 'approved' | 'rejected';
export type SavedProductList = 'wishlist' | 'compare';
export type StockStatus = 'in_stock' | 'out_of_stock' | 'backorder';
}
}
