/**
 * نماذج البيانات المشتركة.
 *
 * تُبقى مطابقة لما تُخرجه App\Http\Resources في الخادم. المفاتيح camelCase
 * لأن موارد Laravel تحوّلها عند الإخراج — انظر docs/API.md
 */

export interface Resource<T> {
  data: T;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * مبلغ مالي كما يُخرجه الخادم.
 *
 * القيمة الخام للحسابات والمقارنات، والنص المنسّق للعرض — فمصدر التنسيق
 * واحد في الخادم ولا يختلف السعر بين صفحة اللوحة والفاتورة والبريد.
 */
export interface Money {
  amount: number;
  currency: string;
  formatted: string;
}

export interface Dimensions {
  width: number;
  height: number;
  depth?: number | null;
  unit: 'cm' | 'inch' | 'm';
}

/* ---------------------------------------------------------------- الحسابات */

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  avatarUrl: string | null;
  isActive: boolean;
  emailVerified: boolean;
  roles: string[];
  createdAt: string;
}

export interface AuthPayload {
  user: User;
  token: string;
}

/* ---------------------------------------------------------------- الكتالوج */

export type PaintingType = 'ready_made' | 'reservable' | 'custom_order';

export type Availability =
  | 'available' | 'reserved' | 'sold'
  | 'out_of_stock' | 'in_production' | 'unavailable';

export interface PaintingImage {
  id: number;
  url: string;
  thumb: string;
  medium: string;
  large: string;
  alt: string | null;
  isMain: boolean;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  imageUrl: string | null;
  isActive: boolean;
  sortOrder: number;
  seo: { title: string; description: string | null };
  paintingsCount?: number;
  children?: Category[];
}

export interface Painting {
  id: number;
  title: string;
  slug: string;
  sku: string;
  shortDescription: string | null;
  price: Money;
  effectivePrice: Money;
  hasDiscount: boolean;
  type: PaintingType;
  typeLabel: string;
  availability: Availability;
  availabilityLabel: string;
  isPurchasable: boolean;
  isReservable: boolean;
  isFeatured: boolean;
  dimensions: Dimensions | null;
  rating: { average: number; count: number };
  category?: Category;
  mainImage?: PaintingImage;
}

export interface DimensionPreset {
  id: number;
  label: string;
  width: number;
  height: number;
  depth: number | null;
  unit: string;
  priceModifier?: Money;
}

export interface PaintingDetail extends Painting {
  description: string | null;
  discountPrice: Money | null;
  stockQuantity: number;
  weight: number | null;
  material: string | null;
  medium: string | null;
  frameInfo: string | null;
  style: string | null;
  colors: string[];
  reservation: { depositPercentage: number; productionDays: number | null } | null;
  seo: { title: string; description: string | null };
  artist: { name: string };
  images: PaintingImage[];
  tags: { name: string; slug: string }[];
  dimensionPresets: DimensionPreset[];
  publishedAt: string | null;
}

/* ------------------------------------------------------------------ السلة */

export interface CartItem {
  id: number;
  quantity: number;
  unitPrice: Money;
  lineTotal: Money;
  painting: Painting;
}

export interface CartTotals {
  subtotal: Money;
  discount: Money;
  shipping: Money;
  tax: Money;
  total: Money;
  itemCount: number;
}

export interface Cart {
  id: number;
  items: CartItem[];
  coupon: { code: string; description: string | null } | null;
  totals: CartTotals;
}

/* ------------------------------------------------------------- الطلبات */

export interface Address {
  id: number;
  label: string | null;
  recipientName: string;
  phone: string;
  country: string;
  city: string;
  district: string | null;
  street: string;
  postalCode: string | null;
  notes: string | null;
  isDefault: boolean;
}

export interface ShippingOption {
  id: number;
  name: string;
  cost: Money;
  isFree: boolean;
  freeShippingThreshold: Money | null;
  estimatedDays: { min: number | null; max: number | null };
}

export interface OrderItem {
  title: string;
  sku: string | null;
  quantity: number;
  unitPrice: Money;
  lineTotal: Money;
  dimensions: Dimensions | null;
  imageUrl: string | null;
  paintingSlug: string | null;
}

export interface Order {
  orderNumber: string;
  status: string;
  statusLabel: string;
  paymentStatus: string;
  paymentStatusLabel: string;
  isCancellable: boolean;
  totals: { subtotal: Money; discount: Money; shipping: Money; tax: Money; total: Money };
  couponCode: string | null;
  items?: OrderItem[];
  shipping: {
    name: string; phone: string; country: string; city: string;
    district: string | null; street: string; postalCode: string | null;
    notes: string | null; trackingNumber: string | null; method?: string;
  };
  customer?: { id: number; name: string; email: string; phone: string };
  timeline: {
    placedAt: string | null; paidAt: string | null;
    shippedAt: string | null; deliveredAt: string | null; cancelledAt: string | null;
  };
}

/* ----------------------------------------------------------- الحجوزات */

export interface ReservationUpdate {
  title: string;
  note: string | null;
  imageUrl: string | null;
  progressPercentage: number | null;
  createdAt: string;
}

export interface Reservation {
  reservationNumber: string;
  status: string;
  statusLabel: string;
  awaitsPayment: boolean;
  progressPercentage: number;
  price: Money | null;
  depositPercentage: number;
  depositAmount: Money | null;
  paidAmount: Money;
  remainingAmount: Money;
  amountDueNow: Money;
  isFullyPaid: boolean;
  customerNote: string | null;
  artistNote: string | null;
  rejectionReason: string | null;
  dueDate: string | null;
  items?: {
    title: string;
    description: string | null;
    dimensions: Dimensions | null;
    preferredColors: string | null;
    paintingSlug: string | null;
    paintingTitle: string | null;
  }[];
  updates?: ReservationUpdate[];
  customer?: { id: number; name: string; email: string; phone: string };
  shipping: {
    name: string | null; phone: string | null; city: string | null;
    district: string | null; street: string | null; trackingNumber: string | null;
  };
  timeline: {
    requestedAt: string | null; acceptedAt: string | null;
    productionStartedAt: string | null; completedAt: string | null; deliveredAt: string | null;
  };
}

/* --------------------------------------------------------------- متفرقات */

export interface Review {
  id: number;
  rating: number;
  title: string | null;
  body: string | null;
  status: string;
  statusLabel: string;
  authorName?: string;
  images?: string[];
  painting?: { title: string; slug: string };
  createdAt: string;
}

export interface AppNotification {
  id: string;
  title: string;
  body: string;
  url: string | null;
  type: string | null;
  readAt: string | null;
  createdAt: string;
}

export interface PlatformMeta {
  appName: string;
  locale: string;
  currency: string;
  tax: { enabled: boolean; percentage: number };
  payment: { driver: string };
}

export interface HomeSections {
  featured: Painting[];
  newest: Painting[];
  bestSellers: Painting[];
  reservable: Painting[];
  categories: Category[];
  banners: {
    title: string | null; subtitle: string | null;
    imageUrl: string; linkUrl: string | null; ctaLabel: string | null;
  }[];
}

export interface AccountOverview {
  orders: { total: number; active: number; completed: number };
  reservations: { total: number; active: number; inProduction: number };
  wishlistCount: number;
  totalSpent: Money;
  unreadNotifications: number;
}
