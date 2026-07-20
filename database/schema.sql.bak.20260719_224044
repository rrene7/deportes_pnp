CREATE TABLE IF NOT EXISTS eventos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(180) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  fecha_evento DATE NOT NULL,
  hora_salida TIME NULL,
  hora_confirmada TINYINT(1) NOT NULL DEFAULT 0,
  lugar_salida VARCHAR(200) NULL,
  distancia_km DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  precio DECIMAL(10,2) NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'PAB',
  cupos INT UNSIGNED NULL,
  inscripciones_abiertas TINYINT(1) NOT NULL DEFAULT 1,
  fecha_cierre_inscripcion DATETIME NULL,
  yappy_numero VARCHAR(20) NOT NULL,
  entrega_kit_texto VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evento_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  edad_min TINYINT UNSIGNED NOT NULL,
  edad_max TINYINT UNSIGNED NULL,
  UNIQUE KEY uq_categoria_evento (evento_id, nombre),
  CONSTRAINT fk_categoria_evento FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('administrador','pagos','kits','consulta') NOT NULL DEFAULT 'administrador',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inscripciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evento_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  identificacion VARCHAR(35) NOT NULL,
  primer_nombre VARCHAR(80) NOT NULL,
  segundo_nombre VARCHAR(80) NULL,
  primer_apellido VARCHAR(80) NOT NULL,
  segundo_apellido VARCHAR(80) NULL,
  fecha_nacimiento DATE NOT NULL,
  edad_evento TINYINT UNSIGNED NOT NULL,
  sexo ENUM('F','M','Otro','No indica') NOT NULL DEFAULT 'No indica',
  correo VARCHAR(160) NOT NULL,
  telefono VARCHAR(30) NOT NULL,
  contacto_emergencia VARCHAR(160) NOT NULL,
  telefono_emergencia VARCHAR(30) NOT NULL,
  talla_camiseta ENUM('XS','S','M','L','XL','2XL','3XL') NOT NULL,
  estado ENUM('pago_pendiente','pago_confirmado','pago_rechazado','cancelada','kit_entregado') NOT NULL DEFAULT 'pago_pendiente',
  acepta_reglamento TINYINT(1) NOT NULL DEFAULT 0,
  ip_registro VARCHAR(45) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evento_identificacion (evento_id, identificacion),
  KEY idx_estado (estado),
  CONSTRAINT fk_inscripcion_evento FOREIGN KEY (evento_id) REFERENCES eventos(id),
  CONSTRAINT fk_inscripcion_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pagos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inscripcion_id INT UNSIGNED NOT NULL UNIQUE,
  metodo VARCHAR(30) NOT NULL DEFAULT 'Yappy',
  yappy_numero VARCHAR(20) NOT NULL,
  nombre_titular VARCHAR(160) NOT NULL,
  referencia VARCHAR(80) NULL UNIQUE,
  fecha_pago DATE NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  archivo_comprobante VARCHAR(255) NOT NULL,
  nombre_original VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  tamano_bytes INT UNSIGNED NOT NULL,
  estado ENUM('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  validado_por INT UNSIGNED NULL,
  fecha_validacion DATETIME NULL,
  observaciones VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pago_inscripcion FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_pago_usuario FOREIGN KEY (validado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entrega_kits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inscripcion_id INT UNSIGNED NOT NULL UNIQUE,
  talla_entregada VARCHAR(10) NOT NULL,
  entregado_por INT UNSIGNED NOT NULL,
  fecha_entrega DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observaciones VARCHAR(500) NULL,
  CONSTRAINT fk_kit_inscripcion FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id) ON DELETE CASCADE,
  CONSTRAINT fk_kit_usuario FOREIGN KEY (entregado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auditoria (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  accion VARCHAR(80) NOT NULL,
  entidad VARCHAR(80) NOT NULL,
  entidad_id INT UNSIGNED NULL,
  detalle JSON NULL,
  ip VARCHAR(45) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auditoria_entidad (entidad, entidad_id),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO eventos (
  nombre, slug, fecha_evento, hora_salida, hora_confirmada, lugar_salida,
  distancia_km, precio, cupos, inscripciones_abiertas, yappy_numero,
  entrega_kit_texto, descripcion
)
SELECT
  'Carrera 5K Policía Nacional', 'carrera-5k-policia-2026', '2026-07-25', NULL, 0, NULL,
  5.00, NULL, NULL, 1, '63977539',
  'Jueves 23 y viernes 24 de julio de 2026, en la Sede A.',
  'Evento deportivo de 5 kilómetros organizado por la Policía Nacional.'
WHERE NOT EXISTS (SELECT 1 FROM eventos WHERE slug = 'carrera-5k-policia-2026');

INSERT INTO categorias (evento_id, nombre, edad_min, edad_max)
SELECT e.id, '18 a 39 años', 18, 39
FROM eventos e
WHERE e.slug = 'carrera-5k-policia-2026'
  AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.evento_id=e.id AND c.nombre='18 a 39 años');

INSERT INTO categorias (evento_id, nombre, edad_min, edad_max)
SELECT e.id, '40 años en adelante', 40, NULL
FROM eventos e
WHERE e.slug = 'carrera-5k-policia-2026'
  AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.evento_id=e.id AND c.nombre='40 años en adelante');
