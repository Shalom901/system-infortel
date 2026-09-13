<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SaleModel;
use App\Models\ClientModel;
use App\Models\CajaModel;
use App\Models\ProductModel;
use App\Services\IdentityLookupService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use PDO;

/**
 * Controlador del Punto de Venta (POS)
 *
 * Gestiona la interfaz de ventas en tiempo real, búsqueda de productos,
 * procesamiento de ventas y generación de comprobantes.
 *
 * @package App\Controllers
 */
class POSController
{
    private PDO         $db;
    private SaleModel   $saleModel;
    private ClientModel $clientModel;
    private CajaModel   $cajaModel;
    private ProductModel $productModel;
    private IdentityLookupService $identityLookup;

    public function __construct(PDO $db)
    {
        $this->db           = $db;
        $this->saleModel    = new SaleModel($db);
        $this->clientModel  = new ClientModel($db);
        $this->cajaModel    = new CajaModel($db);
        $this->productModel = new ProductModel($db);
        $this->identityLookup = new IdentityLookupService();
    }

    // =========================================================================
    // INTERFAZ PRINCIPAL POS
    // =========================================================================

    /**
     * Muestra la interfaz principal del Punto de Venta
     * Verifica que el usuario tenga una caja abierta
     */
    public function index(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        // Verificar sesión activa
        $this->requireAuth();

        $userId = (int)($_SESSION['user_id'] ?? 0);

        // La caja física es compartida; la venta conserva el usuario que la realiza.
        $cajaAbierta = $this->cajaModel->getCajaAbierta(1);

        // Si no tiene caja abierta, redirigir a apertura de caja
        if (!$cajaAbierta) {
            $_SESSION['flash_warning'] = 'Debe abrir una caja antes de realizar ventas.';
            $this->redirect('/caja');
            return;
        }

        // Obtener categorías para los filtros del POS
        $sqlCategorias = "SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre ASC";
        $stmtCat = $this->db->prepare($sqlCategorias);
        $stmtCat->execute();
        $categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

        // Obtener cliente genérico
        $clienteGenerico = $this->clientModel->getClienteGenerico();

        // Configuración del tipo de cambio USD
        $tipoCambio = (float)($_ENV['TIPO_CAMBIO_USD'] ?? TIPO_CAMBIO_USD);

        // Ventas recientes del día (últimas 10)
        $ventasHoy = $this->saleModel->getToday($userId);
        $ventasRecientes = array_slice($ventasHoy, 0, 10);

        // Datos para la vista
        $data = [
            'titulo'          => 'Punto de Venta',
            'caja'            => $cajaAbierta,
            'categorias'      => $categorias,
            'clienteGenerico' => $clienteGenerico,
            'tipoCambio'      => $tipoCambio,
            'ventasRecientes' => $ventasRecientes,
            'igv'             => IGV_PORCENTAJE,
        ];

        $this->render('pos/index', $data);
    }

    // =========================================================================
    // BÚSQUEDA DE PRODUCTOS (AJAX)
    // =========================================================================

    /**
     * Búsqueda rápida de productos para el POS
     * Retorna JSON con lista de productos que coinciden con el término
     *
     * GET /pos/search-product?q=termino&categoria_id=X&limit=20
     */
    public function searchProduct(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $query      = trim($_GET['q']          ?? '');
        $categoriaId = (int)($_GET['categoria_id'] ?? 0);
        $limit      = min((int)($_GET['limit']  ?? 20), 50);

        if (strlen($query) < 1) {
            // Si no hay búsqueda, retornar productos populares/recientes
            $productos = $this->getProductosDestacados($categoriaId, $limit);
        } else {
            $productos = $this->productModel->getForPOS($query, $limit);

            // Filtrar por categoría si se especificó
            if ($categoriaId > 0) {
                $productos = array_filter($productos, fn($p) => (int)$p['categoria_id'] === $categoriaId);
                $productos = array_values($productos);
            }
        }

        // No enviar al navegador nombres de archivos que ya no existen.
        // Esto evita solicitudes 404 y deja que el POS muestre su marcador
        // "Sin imagen" hasta que el producto vuelva a tener una imagen válida.
        $productos = $this->filterUnavailableProductImages($productos);

        $this->jsonResponse([
            'success'   => true,
            'productos' => $productos,
            'total'     => count($productos),
        ]);
    }

    /**
     * Obtiene los datos completos de un producto para agregar al carrito
     *
     * GET /pos/get-product/123
     *
     * @param int $id ID del producto
     */
    public function getProduct(int $id): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $producto = $this->productModel->getById($id);

        if (!$producto) {
            $this->jsonResponse(['success' => false, 'message' => 'Producto no encontrado.'], 404);
            return;
        }

        if (!$producto['activo']) {
            $this->jsonResponse(['success' => false, 'message' => 'El producto no está disponible.'], 422);
            return;
        }

        // Calcular precio con IGV incluido
        $precioConIGV = (float)$producto['precio_venta_pen'];
        $precioSinIGV = $producto['aplica_igv']
            ? round($precioConIGV / 1.18, 4)
            : $precioConIGV;

        $this->jsonResponse([
            'success' => true,
            'producto' => [
                'id'                  => $producto['id'],
                'codigo_interno'      => $producto['codigo_interno'],
                'codigo_barras'       => $producto['codigo_barras'],
                'nombre'              => $producto['nombre'],
                'descripcion'         => $producto['descripcion'],
                'precio_venta'        => $precioConIGV,
                'precio_venta_usd'    => $producto['precio_venta_usd'],
                'stock_actual'        => $producto['stock_actual'],
                'aplica_igv'          => (bool)$producto['aplica_igv'],
                'tipo_afectacion_igv' => $producto['tipo_afectacion_igv'],
                'unidad'              => $producto['unidad_abreviatura'] ?? 'UND',
                'imagen'              => $producto['imagen'],
                'categoria'           => $producto['categoria_nombre'],
            ],
        ]);
    }

    // =========================================================================
    // PROCESAMIENTO DE VENTA
    // =========================================================================

    /**
     * Procesa una venta completa enviada desde el POS
     *
     * POST /pos/procesar-venta
     * Body JSON: {tipo_comprobante, serie, cliente_id, items[], pagos[], totales{}}
     */
    public function procesarVenta(): void
    {
        $this->requireAuth();
        $this->requireAjax();
        $this->requireMethod('POST');

        // Verificar token CSRF
        $this->verifyCsrf();

        $userId = (int)($_SESSION['user_id'] ?? 0);

        // La caja física es compartida; cada venta conserva su usuario_id.
        $cajaAbierta = $this->cajaModel->getCajaAbierta(1);
        if (!$cajaAbierta) {
            $this->jsonResponse(['success' => false, 'message' => 'No tiene una caja abierta.'], 422);
            return;
        }

        // Obtener y decodificar datos del body JSON
        $rawBody = file_get_contents('php://input');
        $input   = json_decode($rawBody, true);

        if (!$input) {
            $this->jsonResponse(['success' => false, 'message' => 'Datos de venta inválidos.'], 400);
            return;
        }

        // Validar datos básicos
        $errors = $this->validateVentaData($input);
        if (!empty($errors)) {
            $this->jsonResponse(['success' => false, 'errors' => $errors, 'message' => 'Verifique los datos de la venta.'], 422);
            return;
        }

        try {
            // Preparar datos para el modelo
            $ventaData = [
                'tipo_comprobante'  => $input['tipo_comprobante'],
                'serie'             => $input['serie'],
                'cliente_id'        => !empty($input['cliente_id']) ? (int)$input['cliente_id'] : null,
                'usuario_id'        => $userId,
                'caja_apertura_id'  => (int)$cajaAbierta['id'],
                'moneda'            => $input['moneda']       ?? 'PEN',
                'tipo_cambio'       => (float)($input['tipo_cambio'] ?? 1.00),
                'subtotal'          => (float)($input['subtotal']    ?? 0),
                'igv'               => (float)($input['igv']         ?? 0),
                'descuento_total'   => (float)($input['descuento']   ?? 0),
                'total'             => (float)$input['total'],
                'notas'             => trim($input['notas'] ?? ''),
                'items'             => $this->sanitizeItems($input['items'] ?? []),
                'pagos'             => $this->sanitizePagos($input['pagos'] ?? []),
            ];

            // Crear la venta
            $resultado = $this->saleModel->create($ventaData);

            $this->jsonResponse([
                'success'            => true,
                'message'            => 'Venta procesada correctamente.',
                'venta_id'           => $resultado['id'],
                'numero_comprobante' => $resultado['numero_comprobante'],
                'total'              => $resultado['total'],
                'tipo_comprobante'   => $resultado['tipo_comprobante'],
                'url_ticket'         => "/pos/ticket/{$resultado['id']}",
                'url_pdf'            => "/pos/pdf/{$resultado['id']}",
                'url_detalle'        => "/ventas/{$resultado['id']}",
            ]);

        } catch (\RuntimeException $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            error_log("Error POS procesarVenta: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Error interno al procesar la venta.'], 500);
        }
    }

    // =========================================================================
    // IMPRESIÓN
    // =========================================================================

    /**
     * Genera el HTML del ticket térmico 80mm para impresión
     *
     * GET /pos/ticket/123
     *
     * @param int $ventaId ID de la venta
     */
    public function printTicket(int $ventaId): void
    {
        $this->requireAuth();

        $venta = $this->saleModel->getById($ventaId);
        if (!$venta) {
            http_response_code(404);
            echo 'Venta no encontrada.';
            return;
        }

        // Obtener configuración de la empresa
        $config = $this->getEmpresaConfig();

        $data = [
            'venta'       => $venta,
            'config'      => $config,
            'ubicacionQr' => $this->generarQrUbicacion($config),
        ];

        // Renderizar directamente la vista del ticket (sin layout)
        include VIEWS_PATH . '/pos/ticket.php';
    }

    /**
     * Genera el PDF del comprobante y lo envía al navegador
     *
     * GET /pos/pdf/123
     *
     * @param int $ventaId ID de la venta
     */
    public function printPDF(int $ventaId): void
    {
        $this->requireAuth();

        $venta = $this->saleModel->getById($ventaId);
        if (!$venta) {
            http_response_code(404);
            echo 'Venta no encontrada.';
            return;
        }

        // 👇 RECUPERAR DATOS REALES DEL CLIENTE (RAZON SOCIAL + DNI/RUC)
        $clienteId = !empty($venta['cliente_id']) ? (int)$venta['cliente_id'] : 1;
        $stmtCli = $this->db->prepare("
            SELECT id, tipo_doc, numero_doc, razon_social, nombres, apellidos, direccion 
            FROM clientes 
            WHERE id = :cid 
            LIMIT 1
        ");
        $stmtCli->execute([':cid' => $clienteId]);
        $cli = $stmtCli->fetch(\PDO::FETCH_ASSOC);

        if ($cli && (int)$cli['id'] > 1) {
            // Priorizar razon_social (donde está guardado SHALOM GONZALO HUAMAN DURAND)
            $nombreReal = !empty($cli['razon_social']) 
                ? $cli['razon_social'] 
                : trim(($cli['nombres'] ?? '') . ' ' . ($cli['apellidos'] ?? ''));

            $venta['cliente_nombre']     = $nombreReal;
            $venta['cliente_numero_doc'] = $cli['numero_doc'] ?: '-';
            $venta['cliente_direccion']  = $cli['direccion'] ?: '';
        }
        // 👆 FIN DEL BLOQUE

        $config = $this->getEmpresaConfig();

        ob_start();
        $data = [
            'venta'       => $venta,
            'config'      => $config,
            'ubicacionQr' => $this->generarQrUbicacion($config),
        ];
        include VIEWS_PATH . '/pos/comprobante_pdf.php';
        $html = ob_get_clean();

        if (class_exists('Dompdf\Dompdf')) {
            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', true);
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $tipoComprobante = ($venta['tipo_comprobante'] === '01') ? 'Factura' : (($venta['tipo_comprobante'] === '03') ? 'Boleta' : 'Ticket');
            $nombreClienteOriginal = $venta['cliente_nombre'] ?? $venta['razon_social'] ?? 'Cliente_General';
            $nombreClienteSlug = function_exists('slug') ? slug($nombreClienteOriginal) : preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim($nombreClienteOriginal)));
            $numeroComprobante = $venta['numero_comprobante'] ?? ($venta['serie'] . '-' . str_pad((string)($venta['numero'] ?? 0), 8, '0', STR_PAD_LEFT));

            $nombreArchivoFinal = sprintf('%s_%s_%s.pdf', $tipoComprobante, $numeroComprobante, $nombreClienteSlug);
            $dompdf->stream($nombreArchivoFinal, ['Attachment' => false]);
            return;
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
        }
    }

    // =========================================================================
    // BÚSQUEDA DE CLIENTES (AJAX)
    // =========================================================================

    public function createClient(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $this->jsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
            return;
        }

        try {
            $id = $this->clientModel->create($data);
            $this->jsonResponse(['success' => true, 'cliente_id' => $id]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Busca clientes por DNI, RUC o nombre para el autocomplete del POS
     *
     * GET /pos/search-client?q=termino
     */
    public function searchClient(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $query = trim($_GET['q'] ?? '');

        if (strlen($query) < 2) {
            $this->jsonResponse(['success' => true, 'clientes' => []]);
            return;
        }

        $clientes = $this->clientModel->search($query, 10);

        $this->jsonResponse([
            'success'  => true,
            'clientes' => $clientes,
        ]);
    }

    public function lookupClientDocument(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        $tipoDocumento = trim((string)($_GET['tipo_doc'] ?? ''));
        $numero = trim((string)($_GET['numero'] ?? ''));
        $longitud = $tipoDocumento === '1' ? 8 : 11;

        if (!in_array($tipoDocumento, ['1', '6'], true) || !preg_match('/^\d{' . $longitud . '}$/', $numero)) {
            $this->jsonResponse(['success' => false, 'message' => 'Ingrese un documento válido.'], 422);
            return;
        }

        try {
            $datos = $this->identityLookup->lookup($tipoDocumento, $numero);
            if ($datos['razon_social'] === '' && $datos['nombre'] === '') {
                $this->jsonResponse(['success' => false, 'message' => 'No se encontró información para el documento.'], 404);
                return;
            }

            $this->jsonResponse(['success' => true, 'data' => $datos]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 502);
        }
    }

    // =========================================================================
    // TIPO DE CAMBIO (AJAX)
    // =========================================================================

    /**
     * Retorna el tipo de cambio actual del USD a PEN
     *
     * GET /pos/tipo-cambio
     */
    public function getExchangeRate(): void
    {
        $this->requireAuth();
        $this->requireAjax();

        // Intentar obtener de la configuración del sistema
        $sqlConfig = "SELECT valor FROM configuracion WHERE clave = 'tipo_cambio_usd' LIMIT 1";
        $stmtConf  = $this->db->prepare($sqlConfig);
        $stmtConf->execute();
        $conf = $stmtConf->fetch(PDO::FETCH_ASSOC);

        $tipoCambio = $conf ? (float)$conf['valor'] : TIPO_CAMBIO_USD;

        $this->jsonResponse([
            'success'     => true,
            'tipo_cambio' => $tipoCambio,
            'moneda_base' => 'PEN',
            'moneda_dest' => 'USD',
        ]);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Obtiene productos destacados o más vendidos para mostrar en el POS
     * sin término de búsqueda
     *
     * @param int $categoriaId Filtrar por categoría (0 = todas)
     * @param int $limit       Máximo de resultados
     * @return array Lista de productos
     */
    private function getProductosDestacados(int $categoriaId, int $limit): array
    {
        $where  = ['p.activo = 1'];
        $params = [];

        if ($categoriaId > 0) {
            $where[]                = 'p.categoria_id = :categoria_id';
            $params[':categoria_id']= $categoriaId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT
                p.id, p.codigo_interno, p.codigo_barras,
                p.nombre, p.precio_venta_pen AS precio_venta, p.precio_mayorista_pen AS precio_mayorista,
                p.stock_actual, p.aplica_igv, p.tipo_afectacion_igv,
                p.imagen_path AS imagen, p.categoria_id,
                u.abreviatura AS unidad,
                c.nombre AS categoria_nombre
            FROM productos p
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            LEFT JOIN categorias      c ON p.categoria_id = c.id
            {$whereClause}
            ORDER BY p.nombre ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quita referencias de imagen que no están disponibles en el directorio
     * público. La base de datos se conserva intacta para no perder la
     * referencia histórica; el administrador puede reemplazar la imagen.
     */
    private function filterUnavailableProductImages(array $productos): array
    {
        $uploadDir = __DIR__ . '/../../public/uploads/products/';
        $backupDir = __DIR__ . '/../../storage/uploads/products/';

        foreach ($productos as &$producto) {
            $imageName = $producto['imagen'] ?? '';
            if ($imageName === '' || basename($imageName) !== $imageName) {
                $producto['imagen'] = null;
                continue;
            }

            $publicPath = $uploadDir . $imageName;
            $backupPath = $backupDir . $imageName;
            if (!is_file($publicPath) && is_file($backupPath)) {
                // Recuperación automática desde el respaldo de una carga válida.
                @copy($backupPath, $publicPath);
            }

            if (!is_file($publicPath) || filesize($publicPath) === 0) {
                $producto['imagen'] = null;
            }
        }
        unset($producto);

        return $productos;
    }

    /**
     * Valida los datos de la venta antes de procesarla
     *
     * @param array $data Datos de la venta
     * @return array Lista de errores de validación
     */
    private function validateVentaData(array $data): array
    {
        $errors = [];

        // Tipo de comprobante obligatorio
        if (empty($data['tipo_comprobante']) || !in_array($data['tipo_comprobante'], ['01', '03', 'NV'])) {
            $errors[] = 'Tipo de comprobante inválido.';
        }

        // Debe tener ítems
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors[] = 'La venta debe tener al menos un producto.';
        }

        // Debe tener al menos un pago
        if (empty($data['pagos']) || !is_array($data['pagos'])) {
            $errors[] = 'Debe seleccionar un método de pago.';
        }

        // Total debe ser positivo
        if (!isset($data['total']) || (float)$data['total'] <= 0) {
            $errors[] = 'El total de la venta debe ser mayor a cero.';
        }

        $tipoComprobante = $data['tipo_comprobante'] ?? '';
        $cliente = $this->obtenerClienteVenta($data['cliente_id'] ?? null);

        if ($tipoComprobante === '01') {
            if (!$cliente) {
                $errors[] = 'La factura requiere un cliente registrado con RUC.';
            } elseif ((int)($cliente['tipo_doc'] ?? 0) !== 6 || !preg_match('/^\d{11}$/', (string)($cliente['numero_doc'] ?? ''))) {
                $errors[] = 'La factura solo puede emitirse a un cliente con RUC válido de 11 dígitos.';
            } elseif (trim((string)($cliente['razon_social'] ?? $cliente['nombres'] ?? '')) === '') {
                $errors[] = 'La factura requiere la razón social o nombre completo del adquiriente.';
            }
        } elseif ($tipoComprobante === '03' && $cliente) {
            $tipoDoc = (int)($cliente['tipo_doc'] ?? 0);
            $numeroDoc = (string)($cliente['numero_doc'] ?? '');
            if ($numeroDoc !== '' && (($tipoDoc === 1 && !preg_match('/^\d{8}$/', $numeroDoc)) || ($tipoDoc === 6 && !preg_match('/^\d{11}$/', $numeroDoc)))) {
                $errors[] = 'El DNI debe tener 8 dígitos o el RUC 11 dígitos.';
            }
            if ($numeroDoc !== '' && trim((string)($cliente['razon_social'] ?? $cliente['nombres'] ?? '')) === '') {
                $errors[] = 'La boleta identificada requiere el nombre completo o razón social del adquiriente.';
            }
        }

        // Validar que el monto de pagos cubra el total
        if (!empty($data['pagos']) && is_array($data['pagos'])) {
            $totalPagado = array_sum(array_column($data['pagos'], 'monto'));
            $total       = (float)($data['total'] ?? 0);
            if ($totalPagado < $total - 0.01) { // Margen de 1 centavo por redondeo
                $errors[] = sprintf(
                    'El monto pagado (S/ %.2f) es menor al total (S/ %.2f).',
                    $totalPagado,
                    $total
                );
            }
        }

        return $errors;
    }

    private function obtenerClienteVenta(mixed $clienteId): ?array
    {
        if (empty($clienteId)) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE id = :id AND activo = 1 LIMIT 1');
        $stmt->execute([':id' => (int)$clienteId]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cliente ?: null;
    }

    /**
     * Sanitiza y normaliza el array de ítems de la venta
     *
     * @param array $items Ítems crudos del request
     * @return array Ítems sanitizados
     */
    private function sanitizeItems(array $items): array
    {
        $sanitized = [];
        foreach ($items as $item) {
            if (empty($item['nombre']) && empty($item['descripcion'])) {
                continue;
            }
            $sanitized[] = [
                'producto_id'         => !empty($item['producto_id']) ? (int)$item['producto_id'] : null,
                'nombre'              => htmlspecialchars(trim($item['nombre'] ?? $item['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'cantidad'            => (float)($item['cantidad']        ?? 1),
                'precio_unit'         => (float)($item['precio_unit']     ?? $item['precio_unitario'] ?? 0),
                'descuento'           => (float)($item['descuento']       ?? 0),
                'subtotal'            => (float)($item['subtotal']        ?? 0),
                'igv'                 => (float)($item['igv']             ?? $item['igv_monto'] ?? 0),
                'total'               => (float)($item['total']           ?? 0),
                'aplica_igv'          => (bool)($item['aplica_igv']       ?? true),
                'tipo_afectacion_igv' => $item['tipo_afectacion_igv']     ?? '10',
            ];
        }
        return $sanitized;
    }

    /**
     * Sanitiza y normaliza el array de pagos de la venta
     *
     * @param array $pagos Pagos crudos del request
     * @return array Pagos sanitizados
     */
    private function sanitizePagos(array $pagos): array
    {
        $metodosValidos = ['efectivo', 'yape', 'plin', 'bcp', 'interbank', 'bbva', 'scotiabank', 'transferencia', 'tarjeta_credito', 'tarjeta_debito', 'usd'];
        $sanitized      = [];

        foreach ($pagos as $pago) {
            $metodo = strtolower(trim($pago['metodo'] ?? $pago['metodo_pago'] ?? ''));
            if ($metodo === 'tarjeta') {
                $metodo = 'tarjeta_credito';
            }
            if (!in_array($metodo, $metodosValidos) || (float)($pago['monto'] ?? 0) <= 0) {
                continue;
            }
            $sanitized[] = [
                'metodo'     => $metodo,
                'monto'      => (float)$pago['monto'],
                'referencia' => htmlspecialchars(trim($pago['referencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?: null,
            ];
        }
        return $sanitized;
    }

    /**
     * Obtiene la configuración de la empresa desde la base de datos
     *
     * @return array Configuración de la empresa
     */
    private function getEmpresaConfig(): array
    {
        try {
            $sql  = "SELECT clave, valor FROM configuracion WHERE clave LIKE 'empresa_%'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($rows as $row) {
                $key          = str_replace('empresa_', '', $row['clave']);
                $config[$key] = $row['valor'];
            }
            return $config;
        } catch (\Exception $e) {
            return [
                'razon_social' => $_ENV['EMPRESA_NOMBRE'] ?? 'EMPRESA DE INFORMÁTICA',
                'ruc'          => $_ENV['EMPRESA_RUC']    ?? '20123456789',
                'direccion'    => $_ENV['EMPRESA_DIR']    ?? 'Pucallpa - Ucayali - Perú',
                'telefono'     => $_ENV['EMPRESA_TEL']    ?? '',
                'email'        => $_ENV['EMPRESA_EMAIL']  ?? '',
            ];
        }
    }

    /** Genera el QR de ubicación para las representaciones impresas. */
    private function generarQrUbicacion(array $config): ?string
    {
        $latitud = trim((string)($_ENV['EMPRESA_LATITUD'] ?? ''));
        $longitud = trim((string)($_ENV['EMPRESA_LONGITUD'] ?? ''));
        if (is_numeric($latitud) && is_numeric($longitud)) {
            $consulta = $latitud . ',' . $longitud;
        } else {
            $direccion = trim(implode(', ', array_filter([
                $config['direccion'] ?? '',
                $config['distrito'] ?? '',
                $config['provincia'] ?? '',
                $config['departamento'] ?? '',
            ])));
            if ($direccion === '') {
                return null;
            }
            $consulta = $direccion;
        }

        $urlMaps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($consulta);

        try {
            $qrCode = QrCode::create($urlMaps)->setSize(260)->setMargin(2);
            return (new PngWriter())->write($qrCode)->getDataUri();
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================================================================
    // HELPERS DE RESPUESTA
    // =========================================================================

    /**
     * Envía una respuesta JSON y termina la ejecución
     *
     * @param array $data       Datos a serializar
     * @param int   $statusCode Código HTTP de respuesta
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        exit;
    }

    /**
     * Renderiza una vista con el layout principal
     *
     * @param string $view Nombre de la vista (ej: 'pos/index')
     * @param array  $data Variables a pasar a la vista
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = VIEWS_PATH . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$viewFile}");
        }
        include VIEWS_PATH . '/layouts/header.php';
        include $viewFile;
        include VIEWS_PATH . '/layouts/footer.php';
    }

    /**
     * Redirige a una URL
     *
     * @param string $url URL de destino
     */
    private function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Verifica que el usuario esté autenticado
     */
    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'message' => 'Sesión expirada.'], 401);
            } else {
                $this->redirect('/auth/login');
            }
        }
    }

    /**
     * Verifica que sea una petición AJAX
     */
    private function requireAjax(): void
    {
        if (!$this->isAjaxRequest()) {
            $this->jsonResponse(['success' => false, 'message' => 'Petición no permitida.'], 403);
        }
    }

    /**
     * Verifica el método HTTP de la petición
     *
     * @param string $method Método esperado: 'POST', 'GET', etc.
     */
    private function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            $this->jsonResponse(['success' => false, 'message' => 'Método no permitido.'], 405);
        }
    }

    /**
     * Verifica el token CSRF para proteger contra ataques CSRF
     */
    private function verifyCsrf(): void
    {
        $rawBody = file_get_contents('php://input');
        $input   = json_decode($rawBody, true);
        $token   = $input['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            $this->jsonResponse(['success' => false, 'message' => 'Token de seguridad inválido.'], 403);
        }
    }

    /**
     * Verifica si la petición es AJAX
     *
     * @return bool True si es petición AJAX
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
