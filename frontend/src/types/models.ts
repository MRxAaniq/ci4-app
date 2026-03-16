export type Role = 'ADMIN' | 'SUPER_ADMIN' | 'BRANCH_MANAGER' | 'SALES';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  branch_id: number;
}

export interface Branch {
  id: number;
  name: string;
  address: string;
  manager_id: number | null;
  status: 'ACTIVE' | 'INACTIVE';
  created_at?: string;
  updated_at?: string;
}

export interface Product {
  id: number;
  name: string;
  sku: string;
  cost_price: string | number;
  sale_price: string | number;
  tax_percentage: string | number;
  status: 'ACTIVE' | 'INACTIVE';
  created_at?: string;
  updated_at?: string;
}

export interface InventoryRow {
  branch_id: number;
  product_id: number;
  quantity: number;
  updated_at: string;
  name: string;
  sku: string;
  sale_price: string | number;
  tax_percentage: string | number;
  status: 'ACTIVE' | 'INACTIVE';
}

export interface InventoryMovement {
  id: number;
  branch_id: number;
  product_id: number;
  delta_qty: number;
  qty_before: number;
  qty_after: number;
  ref_type: 'ORDER' | 'TRANSFER' | 'ADJUSTMENT';
  ref_id: number;
  actor_user_id: number | null;
  actor_name?: string | null;
  actor_email?: string | null;
  note?: string | null;
  created_at: string;
  product_name: string;
  product_sku: string;
}

export interface OrderItem {
  id: number;
  order_id: number;
  product_id: number;
  quantity: number;
  price: string | number;
  tax: string | number;
  name?: string;
  sku?: string;
}

export interface Order {
  id: number;
  branch_id: number;
  user_id: number;
  subtotal: string;
  tax_total: string;
  grand_total: string;
  status: 'DRAFT' | 'PENDING' | 'SUBMITTED' | 'CANCELLED';
  created_at?: string;
  updated_at?: string;
  items?: OrderItem[];
}

export interface StockTransfer {
  id: number;
  from_branch: number;
  to_branch: number;
  product_id: number;
  quantity: number;
  status: 'DRAFT' | 'SENT' | 'RECEIVED' | 'CANCELLED';
  created_by: number | null;
  sent_at?: string | null;
  received_at?: string | null;
  created_at?: string;
  updated_at?: string;
  from_branch_name?: string;
  to_branch_name?: string;
  product_name?: string;
  sku?: string;
}
