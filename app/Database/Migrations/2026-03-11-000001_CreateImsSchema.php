<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateImsSchema extends Migration
{
    public function up()
    {
        // Tables
        $this->db->query(<<<SQL
CREATE TABLE users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(150)     NOT NULL,
  email         VARCHAR(191)     NOT NULL,
  password_hash VARCHAR(255)     NOT NULL,
  role          ENUM('ADMIN','BRANCH_MANAGER','SALES') NOT NULL,
  status        ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status)
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE branches (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150)     NOT NULL,
  address     VARCHAR(255)     NOT NULL,
  manager_id  BIGINT UNSIGNED NULL,
  status      ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_branches_manager (manager_id),
  CONSTRAINT fk_branches_manager
    FOREIGN KEY (manager_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE products (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(200)     NOT NULL,
  sku            VARCHAR(80)      NOT NULL,
  cost_price     DECIMAL(13,2)    NOT NULL DEFAULT 0.00,
  sale_price     DECIMAL(13,2)    NOT NULL DEFAULT 0.00,
  tax_percentage DECIMAL(5,2)     NOT NULL DEFAULT 0.00,
  status         ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_status (status),
  KEY idx_products_name (name)
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE inventory (
  branch_id  BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity   INT            NOT NULL DEFAULT 0,
  updated_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (branch_id, product_id),
  KEY idx_inventory_product (product_id),
  CONSTRAINT fk_inventory_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_inventory_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_inventory_non_negative CHECK (quantity >= 0)
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE inventory_movements (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id     BIGINT UNSIGNED NOT NULL,
  product_id    BIGINT UNSIGNED NOT NULL,
  delta_qty     INT             NOT NULL,
  qty_before    INT             NOT NULL,
  qty_after     INT             NOT NULL,
  ref_type      ENUM('ORDER','TRANSFER','ADJUSTMENT') NOT NULL,
  ref_id        BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  note          VARCHAR(255)    NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movements_branch_product_time (branch_id, product_id, created_at),
  KEY idx_movements_ref (ref_type, ref_id),
  CONSTRAINT fk_movements_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_movements_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_movements_actor
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_movements_after_non_negative CHECK (qty_after >= 0)
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE orders (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id   BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  subtotal    DECIMAL(13,2)    NOT NULL DEFAULT 0.00,
  tax_total   DECIMAL(13,2)    NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(13,2)    NOT NULL DEFAULT 0.00,
  status      ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_branch_status_time (branch_id, status, created_at),
  KEY idx_orders_user_time (user_id, created_at),
  CONSTRAINT fk_orders_branch
    FOREIGN KEY (branch_id) REFERENCES branches(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE order_items (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity   INT             NOT NULL,
  price      DECIMAL(13,2)   NOT NULL,
  tax        DECIMAL(13,2)   NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_items_order_product (order_id, product_id),
  KEY idx_order_items_order (order_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_order_items_qty_positive CHECK (quantity > 0)
) ENGINE=InnoDB
SQL);

        $this->db->query(<<<SQL
CREATE TABLE stock_transfers (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_branch   BIGINT UNSIGNED NOT NULL,
  to_branch     BIGINT UNSIGNED NOT NULL,
  product_id    BIGINT UNSIGNED NOT NULL,
  quantity      INT             NOT NULL,
  status        ENUM('DRAFT','SENT','RECEIVED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  created_by    BIGINT UNSIGNED NULL,
  sent_at       TIMESTAMP       NULL,
  received_at   TIMESTAMP       NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_transfers_status_time (status, created_at),
  KEY idx_transfers_from_status (from_branch, status),
  KEY idx_transfers_to_status (to_branch, status),
  KEY idx_transfers_product (product_id),
  CONSTRAINT fk_transfers_from_branch
    FOREIGN KEY (from_branch) REFERENCES branches(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_transfers_to_branch
    FOREIGN KEY (to_branch) REFERENCES branches(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_transfers_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_transfers_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT chk_transfers_qty_positive CHECK (quantity > 0),
  CONSTRAINT chk_transfers_distinct_branches CHECK (from_branch <> to_branch)
) ENGINE=InnoDB
SQL);

        // Triggers: prevent negative inventory even if CHECK constraints are ignored
        $this->db->query(<<<SQL
CREATE TRIGGER trg_inventory_bi_non_negative
BEFORE INSERT ON inventory
FOR EACH ROW
BEGIN
  IF NEW.quantity < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory quantity cannot be negative';
  END IF;
END
SQL);

        $this->db->query(<<<SQL
CREATE TRIGGER trg_inventory_bu_non_negative
BEFORE UPDATE ON inventory
FOR EACH ROW
BEGIN
  IF NEW.quantity < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory quantity cannot be negative';
  END IF;
END
SQL);

        // Stored procedures: safe concurrent stock changes with row locks
        // Note: we use VARCHAR for ref_type to avoid edge cases with ENUM routine params.
        $this->db->query(<<<SQL
CREATE PROCEDURE sp_inventory_increase (
  IN p_branch_id BIGINT UNSIGNED,
  IN p_product_id BIGINT UNSIGNED,
  IN p_qty INT,
  IN p_ref_type VARCHAR(20),
  IN p_ref_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_note VARCHAR(255)
)
BEGIN
  DECLARE v_before INT DEFAULT 0;

  IF p_qty <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Increase quantity must be > 0';
  END IF;

  START TRANSACTION;

  INSERT INTO inventory(branch_id, product_id, quantity)
  VALUES(p_branch_id, p_product_id, 0)
  ON DUPLICATE KEY UPDATE quantity = quantity;

  SELECT quantity INTO v_before
  FROM inventory
  WHERE branch_id = p_branch_id AND product_id = p_product_id
  FOR UPDATE;

  UPDATE inventory
  SET quantity = quantity + p_qty
  WHERE branch_id = p_branch_id AND product_id = p_product_id;

  INSERT INTO inventory_movements(
    branch_id, product_id, delta_qty, qty_before, qty_after, ref_type, ref_id, actor_user_id, note
  )
  VALUES(
    p_branch_id, p_product_id, p_qty, v_before, v_before + p_qty,
    CASE
      WHEN p_ref_type IN ('ORDER','TRANSFER','ADJUSTMENT') THEN p_ref_type
      ELSE 'ADJUSTMENT'
    END,
    p_ref_id, p_actor_user_id, p_note
  );

  COMMIT;
END
SQL);

        $this->db->query(<<<SQL
CREATE PROCEDURE sp_inventory_decrease (
  IN p_branch_id BIGINT UNSIGNED,
  IN p_product_id BIGINT UNSIGNED,
  IN p_qty INT,
  IN p_ref_type VARCHAR(20),
  IN p_ref_id BIGINT UNSIGNED,
  IN p_actor_user_id BIGINT UNSIGNED,
  IN p_note VARCHAR(255)
)
BEGIN
  DECLARE v_before INT DEFAULT 0;

  IF p_qty <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Decrease quantity must be > 0';
  END IF;

  START TRANSACTION;

  INSERT INTO inventory(branch_id, product_id, quantity)
  VALUES(p_branch_id, p_product_id, 0)
  ON DUPLICATE KEY UPDATE quantity = quantity;

  SELECT quantity INTO v_before
  FROM inventory
  WHERE branch_id = p_branch_id AND product_id = p_product_id
  FOR UPDATE;

  IF v_before < p_qty THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
  END IF;

  UPDATE inventory
  SET quantity = quantity - p_qty
  WHERE branch_id = p_branch_id AND product_id = p_product_id;

  INSERT INTO inventory_movements(
    branch_id, product_id, delta_qty, qty_before, qty_after, ref_type, ref_id, actor_user_id, note
  )
  VALUES(
    p_branch_id, p_product_id, -p_qty, v_before, v_before - p_qty,
    CASE
      WHEN p_ref_type IN ('ORDER','TRANSFER','ADJUSTMENT') THEN p_ref_type
      ELSE 'ADJUSTMENT'
    END,
    p_ref_id, p_actor_user_id, p_note
  );

  COMMIT;
END
SQL);
    }

    public function down()
    {
        // Drop routines & triggers first
        $this->db->query('DROP PROCEDURE IF EXISTS sp_inventory_decrease');
        $this->db->query('DROP PROCEDURE IF EXISTS sp_inventory_increase');

        $this->db->query('DROP TRIGGER IF EXISTS trg_inventory_bu_non_negative');
        $this->db->query('DROP TRIGGER IF EXISTS trg_inventory_bi_non_negative');

        // Drop tables in FK-safe order
        $this->db->query('DROP TABLE IF EXISTS order_items');
        $this->db->query('DROP TABLE IF EXISTS orders');
        $this->db->query('DROP TABLE IF EXISTS stock_transfers');
        $this->db->query('DROP TABLE IF EXISTS inventory_movements');
        $this->db->query('DROP TABLE IF EXISTS inventory');
        $this->db->query('DROP TABLE IF EXISTS products');
        $this->db->query('DROP TABLE IF EXISTS branches');
        $this->db->query('DROP TABLE IF EXISTS users');
    }
}
