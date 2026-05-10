-- AmplifyCan Customer Portal — SQLite schema

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  free_delivery INTEGER NOT NULL DEFAULT 0,
  notes TEXT,
  -- Per-customer email notification preferences (defaults: shipped + delivered ON)
  notify_received      INTEGER NOT NULL DEFAULT 0,
  notify_in_production INTEGER NOT NULL DEFAULT 0,
  notify_shipped       INTEGER NOT NULL DEFAULT 1,
  notify_delivered     INTEGER NOT NULL DEFAULT 1,
  notify_invoiced      INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER REFERENCES customers(id),
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  first_name TEXT,
  last_name TEXT,
  is_admin INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS addresses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  label TEXT NOT NULL,
  street1 TEXT,
  street2 TEXT,
  city TEXT,
  state TEXT,
  zip TEXT,
  is_default_ship INTEGER NOT NULL DEFAULT 0,
  is_default_bill INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  name TEXT NOT NULL,
  spec TEXT,
  icon TEXT NOT NULL DEFAULT '📄',
  unit_price NUMERIC NOT NULL,
  unit_qty INTEGER NOT NULL DEFAULT 1,
  price_label TEXT,
  multi_line INTEGER NOT NULL DEFAULT 0,
  fulfillment TEXT NOT NULL DEFAULT 'inhouse',
  active INTEGER NOT NULL DEFAULT 1,
  last_ordered_at TEXT
);

CREATE TABLE IF NOT EXISTS pricing_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  rule_key TEXT NOT NULL,
  label TEXT NOT NULL,
  price NUMERIC NOT NULL,
  UNIQUE(customer_id, rule_key)
);

CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  user_id INTEGER NOT NULL REFERENCES users(id),
  type TEXT NOT NULL,
  name TEXT NOT NULL,
  project TEXT,
  status TEXT NOT NULL DEFAULT 'Submitted',
  subtotal NUMERIC NOT NULL DEFAULT 0,
  tax NUMERIC NOT NULL DEFAULT 0,
  total NUMERIC NOT NULL DEFAULT 0,
  ship_to_id INTEGER REFERENCES addresses(id),
  bill_to_id INTEGER REFERENCES addresses(id),
  notes TEXT,
  monday_item_id TEXT,
  placed_by_admin_id INTEGER REFERENCES users(id),  -- set when an admin placed this on behalf of the customer
  tracking TEXT,
  last_notified_status TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS order_lines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id),
  description TEXT NOT NULL,
  amount NUMERIC NOT NULL,
  metadata TEXT
);

CREATE TABLE IF NOT EXISTS order_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  order_id INTEGER NOT NULL REFERENCES orders(id),
  filename TEXT NOT NULL,
  path TEXT NOT NULL,
  size_bytes INTEGER,
  uploaded_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Inline feedback from admins, picked up by Claude in chat sessions to drive code changes.
CREATE TABLE IF NOT EXISTS feedback (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_user_id   INTEGER REFERENCES users(id),
  customer_id     INTEGER REFERENCES customers(id),
  page_url        TEXT,
  context_json    TEXT,
  message         TEXT NOT NULL,
  status          TEXT NOT NULL DEFAULT 'open',  -- open | resolved
  claude_note     TEXT,
  c