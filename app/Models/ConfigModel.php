<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use Exception;

/**
 * Modelo de Configuración del Sistema
 * 
 * Gestiona todas las configuraciones del sistema de facturación:
 * datos de empresa, SUNAT, series documentales, tipo de cambio y correo.
 * 
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */
class ConfigModel
{
    /** @var PDO Instancia de conexión PDO */
    private PDO $db;

    /** @var string Clave de cifrado para credenciales sensibles */
    private string $encryptionKey;

    /**
     * Constructor del modelo
     * 
     * @param PDO $db Instancia PDO inyectada
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        // La clave de cifrado viene de la variable de entorno APP_KEY
        $this->encryptionKey = $_ENV['APP_KEY'] ?? 'default_key_change_in_production';
    }

    // =========================================================
    // SECCIÓN: CONFIGURACIÓN DE EMPRESA
    // =========================================================

    /**
     * Obtiene todos los datos de configuración de la empresa.
     * 
     * Lee la tabla `configuracion` y retorna un array asociativo
     * con todos los parámetros de empresa (RUC, razón social, logo, etc.).
     * 
     * @return array<string, mixed> Datos de configuración de la empresa
     */
    public function getEmpresa(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    clave,
                    valor
                FROM configuracion
                WHERE grupo = 'empresa'
                ORDER BY clave ASC
            ");
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir array de filas clave=>valor en array asociativo plano
            $config = [];
            foreach ($filas as $fila) {
                $config[$fila['clave']] = $fila['valor'];
            }

            // Valores por defecto si no existen en BD
            return array_merge([
                'ruc'              => '',
                'razon_social'     => '',
                'nombre_comercial' => '',
                'direccion'        => '',
                'ubigeo'           => '',
                'departamento'     => '',
                'provincia'        => '',
                'distrito'         => '',
                'telefono'         => '',
                'email'            => '',
                'web'              => '',
                'logo'             => '',
                'igv_porcentaje'   => '18',
                'moneda_defecto'   => 'PEN',
            ], $config);

        } catch (PDOException $e) {
            error_log("ConfigModel::getEmpresa - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda los datos de configuración de la empresa.
     * 
     * Realiza un UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) para cada
     * par clave=>valor del array recibido dentro del grupo 'empresa'.
     * 
     * @param array<string, mixed> $data Array asociativo con los campos a guardar
     * @return bool true si se guardó correctamente, false en caso de error
     */
    public function saveEmpresa(array $data): bool
    {
        // Campos permitidos para evitar mass-assignment
        $camposPermitidos = [
            'ruc', 'razon_social', 'nombre_comercial', 'direccion',
            'ubigeo', 'departamento', 'provincia', 'distrito',
            'telefono', 'email', 'web', 'logo',
            'igv_porcentaje', 'moneda_defecto',
        ];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO configuracion (grupo, clave, valor, actualizado_en)
                VALUES ('empresa', :clave, :valor, NOW())
                ON DUPLICATE KEY UPDATE
                    valor        = VALUES(valor),
                    actualizado_en = NOW()
            ");

            foreach ($data as $clave => $valor) {
                // Solo guardar campos permitidos (whitelist)
                if (!in_array($clave, $camposPermitidos, true)) {
                    continue;
                }
                $stmt->execute([
                    ':clave' => $clave,
                    ':valor' => (string) $valor,
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ConfigModel::saveEmpresa - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: CONFIGURACIÓN SUNAT
    // =========================================================

    /**
     * Obtiene la configuración de SUNAT (SOE/SEE-SOL).
     * 
     * Las credenciales SOL (usuario y clave) se retornan descifradas
     * para su uso interno, pero NUNCA deben enviarse al frontend en texto plano.
     * 
     * @return array<string, mixed> Configuración SUNAT
     */
    public function getSunat(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT clave, valor
                FROM configuracion
                WHERE grupo = 'sunat'
                ORDER BY clave ASC
            ");
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($filas as $fila) {
                $config[$fila['clave']] = $fila['valor'];
            }

            // Descifrar credenciales sensibles si existen
            if (!empty($config['clave_sol_cifrada'])) {
                $config['clave_sol'] = $this->decryptValue($config['clave_sol_cifrada']);
                unset($config['clave_sol_cifrada']); // No exponer el cifrado
            }

            return array_merge([
                'ruc_sunat'          => '',
                'usuario_sol'        => '',
                'clave_sol'          => '',
                'modo'               => 'beta',
                'cert_path'          => '',
                'cert_pass'          => '',
                'ws_factura_beta'    => $_ENV['SUNAT_WS_FACTURA_BETA'] ?? '',
                'ws_factura_prod'    => $_ENV['SUNAT_WS_FACTURA_PROD'] ?? '',
                'ws_baja_beta'       => $_ENV['SUNAT_WS_BAJA_BETA'] ?? '',
                'ws_baja_prod'       => $_ENV['SUNAT_WS_BAJA_PROD'] ?? '',
                'envio_automatico'   => '0',
            ], $config);

        } catch (PDOException $e) {
            error_log("ConfigModel::getSunat - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda la configuración SUNAT.
     * 
     * Las credenciales SOL (clave) se cifran antes de guardar en BD.
     * La clave SOL nunca se almacena en texto plano.
     * 
     * @param array<string, mixed> $data Configuración SUNAT a guardar
     * @return bool true si se guardó correctamente
     */
    public function saveSunat(array $data): bool
    {
        $camposPermitidos = [
            'ruc_sunat', 'usuario_sol', 'modo',
            'cert_path', 'cert_pass', 'envio_automatico',
        ];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO configuracion (grupo, clave, valor, actualizado_en)
                VALUES ('sunat', :clave, :valor, NOW())
                ON DUPLICATE KEY UPDATE
                    valor        = VALUES(valor),
                    actualizado_en = NOW()
            ");

            foreach ($data as $clave => $valor) {
                if (!in_array($clave, $camposPermitidos, true)) {
                    continue;
                }
                $stmt->execute([
                    ':clave' => $clave,
                    ':valor' => (string) $valor,
                ]);
            }

            // Cifrar y guardar la clave SOL por separado si fue proporcionada
            if (!empty($data['clave_sol'])) {
                $claveCifrada = $this->encryptValue($data['clave_sol']);
                $stmt->execute([
                    ':clave' => 'clave_sol_cifrada',
                    ':valor' => $claveCifrada,
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ConfigModel::saveSunat - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: SERIES DOCUMENTALES
    // =========================================================

    /**
     * Obtiene todas las series documentales activas.
     * 
     * Retorna las series configuradas para facturas (F), boletas (B),
     * notas de crédito (FC/BC), notas de débito (FD/BD), etc.
     * 
     * @return array<int, array<string, mixed>> Lista de series activas
     */
    public function getSeries(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    tipo_documento,
                    serie,
                    correlativo_actual,
                    activo,
                    creado_en,
                    actualizado_en
                FROM series_documentales
                WHERE activo = 1
                ORDER BY tipo_documento ASC, serie ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("ConfigModel::getSeries - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda o actualiza las series documentales.
     * 
     * Permite configurar la serie inicial y el correlativo para cada
     * tipo de documento electrónico (facturas, boletas, etc.).
     * 
     * @param array<int, array<string, mixed>> $data Array de series a guardar
     * @return bool true si se guardó correctamente
     */
    public function saveSeries(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO series_documentales 
                    (tipo_documento, serie, correlativo_actual, activo, actualizado_en)
                VALUES 
                    (:tipo_documento, :serie, :correlativo_actual, :activo, NOW())
                ON DUPLICATE KEY UPDATE
                    serie              = VALUES(serie),
                    correlativo_actual = VALUES(correlativo_actual),
                    activo             = VALUES(activo),
                    actualizado_en     = NOW()
            ");

            foreach ($data as $serie) {
                // Validar que la serie tenga el formato correcto (F001, B001, etc.)
                if (!preg_match('/^[FBCDE][A-Z0-9]{3}$/', (string) $serie['serie'])) {
                    continue; // Saltar series con formato inválido
                }
                $stmt->execute([
                    ':tipo_documento'    => $serie['tipo_documento'],
                    ':serie'             => strtoupper((string) $serie['serie']),
                    ':correlativo_actual'=> (int) ($serie['correlativo_actual'] ?? 1),
                    ':activo'            => (int) ($serie['activo'] ?? 1),
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ConfigModel::saveSeries - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: TIPO DE CAMBIO
    // =========================================================

    /**
     * Obtiene el tipo de cambio vigente (USD a PEN).
     * 
     * Retorna el registro más reciente de tipo de cambio, ya sea
     * actualizado automáticamente desde SUNAT/SBS o de forma manual.
     * 
     * @return array<string, mixed>|null Tipo de cambio actual o null si no existe
     */
    public function getTipoCambio(): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    moneda_origen,
                    moneda_destino,
                    tipo_compra,
                    tipo_venta,
                    fecha,
                    fuente,
                    creado_en
                FROM tipos_cambio
                WHERE moneda_origen = 'USD'
                  AND moneda_destino = 'PEN'
                ORDER BY fecha DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ?: null;

        } catch (PDOException $e) {
            error_log("ConfigModel::getTipoCambio - Error PDO: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualiza o inserta el tipo de cambio del día.
     * 
     * Si ya existe un registro para la fecha actual, lo actualiza.
     * Si no existe, crea uno nuevo. Registra la fuente (manual/sunat/sbs).
     * 
     * @param float  $tipoCompra Tipo de cambio compra (USD → PEN)
     * @param float  $tipoVenta  Tipo de cambio venta  (USD → PEN)
     * @param string $fuente     Origen del dato: 'manual', 'sunat', 'sbs'
     * @param string|null $fecha Fecha en formato Y-m-d (por defecto hoy)
     * @return bool true si se actualizó correctamente
     */
    public function updateTipoCambio(
        float $tipoCompra,
        float $tipoVenta,
        string $fuente = 'manual',
        ?string $fecha = null
    ): bool {
        $fecha = $fecha ?? date('Y-m-d');

        try {
            $stmt = $this->db->prepare("
                INSERT INTO tipos_cambio 
                    (moneda_origen, moneda_destino, tipo_compra, tipo_venta, fecha, fuente, creado_en)
                VALUES 
                    ('USD', 'PEN', :tipo_compra, :tipo_venta, :fecha, :fuente, NOW())
                ON DUPLICATE KEY UPDATE
                    tipo_compra = VALUES(tipo_compra),
                    tipo_venta  = VALUES(tipo_venta),
                    fuente      = VALUES(fuente),
                    creado_en   = NOW()
            ");
            $stmt->execute([
                ':tipo_compra' => $tipoCompra,
                ':tipo_venta'  => $tipoVenta,
                ':fecha'       => $fecha,
                ':fuente'      => $fuente,
            ]);

            // Actualizar también la variable de configuración general
            $stmtConfig = $this->db->prepare("
                INSERT INTO configuracion (grupo, clave, valor, actualizado_en)
                VALUES ('general', 'tipo_cambio_usd', :valor, NOW())
                ON DUPLICATE KEY UPDATE
                    valor = VALUES(valor),
                    actualizado_en = NOW()
            ");
            $stmtConfig->execute([':valor' => (string) $tipoVenta]);

            return true;

        } catch (PDOException $e) {
            error_log("ConfigModel::updateTipoCambio - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el historial de tipos de cambio.
     * 
     * @param int $limit Número máximo de registros a retornar (por defecto 30)
     * @return array<int, array<string, mixed>> Historial de tipos de cambio
     */
    public function getHistorialTipoCambio(int $limit = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    moneda_origen,
                    moneda_destino,
                    tipo_compra,
                    tipo_venta,
                    DATE_FORMAT(fecha, '%d/%m/%Y') AS fecha_formateada,
                    fecha,
                    fuente,
                    DATE_FORMAT(creado_en, '%d/%m/%Y %H:%i') AS creado_en_formateado
                FROM tipos_cambio
                WHERE moneda_origen = 'USD'
                  AND moneda_destino = 'PEN'
                ORDER BY fecha DESC, id DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("ConfigModel::getHistorialTipoCambio - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // SECCIÓN: CONFIGURACIÓN DE CORREO
    // =========================================================

    /**
     * Obtiene la configuración de correo electrónico (SMTP).
     * 
     * La contraseña SMTP se retorna enmascarada para el frontend
     * (solo se muestran asteriscos) por seguridad.
     * 
     * @param bool $incluirPassword Si true, incluye la contraseña descifrada (solo para uso interno)
     * @return array<string, mixed> Configuración de correo
     */
    public function getEmail(bool $incluirPassword = false): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT clave, valor
                FROM configuracion
                WHERE grupo = 'email'
                ORDER BY clave ASC
            ");
            $stmt->execute();
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($filas as $fila) {
                $config[$fila['clave']] = $fila['valor'];
            }

            // Descifrar contraseña solo cuando se requiera internamente
            if ($incluirPassword && !empty($config['smtp_pass_cifrada'])) {
                $config['smtp_pass'] = $this->decryptValue($config['smtp_pass_cifrada']);
            } elseif (!$incluirPassword && !empty($config['smtp_pass_cifrada'])) {
                // Mostrar solo asteriscos al frontend
                $config['smtp_pass'] = '••••••••';
            }
            unset($config['smtp_pass_cifrada']); // Nunca exponer el cifrado

            return array_merge([
                'smtp_host'       => 'smtp.gmail.com',
                'smtp_port'       => '587',
                'smtp_usuario'    => '',
                'smtp_pass'       => '',
                'smtp_cifrado'    => 'tls',
                'remitente_email' => '',
                'remitente_nombre'=> $_ENV['APP_NAME'] ?? 'Sistema de Facturación',
            ], $config);

        } catch (PDOException $e) {
            error_log("ConfigModel::getEmail - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda la configuración de correo electrónico.
     * 
     * La contraseña SMTP se cifra antes de persistir en la BD.
     * 
     * @param array<string, mixed> $data Configuración de correo a guardar
     * @return bool true si se guardó correctamente
     */
    public function saveEmail(array $data): bool
    {
        $camposPermitidos = [
            'smtp_host', 'smtp_port', 'smtp_usuario',
            'smtp_cifrado', 'remitente_email', 'remitente_nombre',
        ];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO configuracion (grupo, clave, valor, actualizado_en)
                VALUES ('email', :clave, :valor, NOW())
                ON DUPLICATE KEY UPDATE
                    valor = VALUES(valor),
                    actualizado_en = NOW()
            ");

            foreach ($data as $clave => $valor) {
                if (!in_array($clave, $camposPermitidos, true)) {
                    continue;
                }
                $stmt->execute([
                    ':clave' => $clave,
                    ':valor' => (string) $valor,
                ]);
            }

            // Cifrar y guardar contraseña SMTP si fue proporcionada
            if (!empty($data['smtp_pass']) && $data['smtp_pass'] !== '••••••••') {
                $passCifrada = $this->encryptValue($data['smtp_pass']);
                $stmt->execute([
                    ':clave' => 'smtp_pass_cifrada',
                    ':valor' => $passCifrada,
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("ConfigModel::saveEmail - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: CONFIGURACIÓN GENERAL (GETTER/SETTER GENÉRICO)
    // =========================================================

    /**
     * Obtiene un valor de configuración por grupo y clave.
     * 
     * @param string $grupo  Grupo de configuración (empresa, sunat, email, etc.)
     * @param string $clave  Clave de la configuración
     * @param mixed  $defecto Valor por defecto si no existe
     * @return mixed Valor de la configuración
     */
    public function get(string $grupo, string $clave, mixed $defecto = null): mixed
    {
        try {
            $stmt = $this->db->prepare("
                SELECT valor
                FROM configuracion
                WHERE grupo = :grupo AND clave = :clave
                LIMIT 1
            ");
            $stmt->execute([':grupo' => $grupo, ':clave' => $clave]);
            $resultado = $stmt->fetchColumn();
            return $resultado !== false ? $resultado : $defecto;

        } catch (PDOException $e) {
            error_log("ConfigModel::get - Error PDO: " . $e->getMessage());
            return $defecto;
        }
    }

    /**
     * Establece un valor de configuración.
     * 
     * @param string $grupo  Grupo de configuración
     * @param string $clave  Clave de la configuración
     * @param mixed  $valor  Valor a guardar
     * @return bool true si se guardó correctamente
     */
    public function set(string $grupo, string $clave, mixed $valor): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO configuracion (grupo, clave, valor, actualizado_en)
                VALUES (:grupo, :clave, :valor, NOW())
                ON DUPLICATE KEY UPDATE
                    valor = VALUES(valor),
                    actualizado_en = NOW()
            ");
            $stmt->execute([
                ':grupo' => $grupo,
                ':clave' => $clave,
                ':valor' => (string) $valor,
            ]);
            return true;

        } catch (PDOException $e) {
            error_log("ConfigModel::set - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: MÉTODOS PRIVADOS DE CIFRADO
    // =========================================================

    /**
     * Cifra un valor sensible usando AES-256-CBC.
     * 
     * El resultado incluye el IV codificado en base64 para permitir
     * el descifrado posterior sin necesidad de almacenarlo por separado.
     * 
     * @param string $valor Valor en texto plano a cifrar
     * @return string Valor cifrado en formato "base64(iv):base64(encrypted)"
     */
    private function encryptValue(string $valor): string
    {
        // Usar los primeros 32 bytes de la clave (AES-256 requiere 32 bytes)
        $clave = substr(hash('sha256', $this->encryptionKey, true), 0, 32);
        $iv    = random_bytes(16);

        $cifrado = openssl_encrypt($valor, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);

        if ($cifrado === false) {
            throw new Exception("Error al cifrar el valor");
        }

        return base64_encode($iv) . ':' . base64_encode($cifrado);
    }

    /**
     * Descifra un valor previamente cifrado con encryptValue().
     * 
     * @param string $valorCifrado Valor en formato "base64(iv):base64(encrypted)"
     * @return string Valor descifrado en texto plano
     */
    private function decryptValue(string $valorCifrado): string
    {
        $partes = explode(':', $valorCifrado, 2);
        if (count($partes) !== 2) {
            return ''; // Formato inválido
        }

        $clave  = substr(hash('sha256', $this->encryptionKey, true), 0, 32);
        $iv     = base64_decode($partes[0]);
        $datos  = base64_decode($partes[1]);

        $descifrado = openssl_decrypt($datos, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);

        return $descifrado !== false ? $descifrado : '';
    }
}
