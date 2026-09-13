<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\CategoryModel;
use App\Models\SupplierModel;
use App\Services\BarcodeService;
use PDO;
use RuntimeException;

/**
 * Controlador de Productos
 * 
 * Maneja el CRUD completo de productos, gestión de inventario,
 * códigos de barras, importación/exportación Excel y búsqueda AJAX.
 * 
 * @package App\Controllers
 */
class ProductController
{
    private ProductModel  $productModel;
    private CategoryModel $categoryModel;
    private SupplierModel $supplierModel;
    private BarcodeService $barcodeService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db             = $db;
        $this->productModel   = new ProductModel($db);
        $this->categoryModel  = new CategoryModel($db);
        $this->supplierModel  = new SupplierModel($db);
        $this->barcodeService = new BarcodeService();
    }

    // =========================================================================
    // LISTADO
    // =========================================================================

    /**
     * Muestra la lista de productos con filtros y paginación
     * GET /productos
     */
    public function index(): void
    {
        $this->requireAuth();

        $filters = [
            'busqueda'    => $this->sanitize($_GET['q']             ?? ''),
            'categoria_id' => (int)($_GET['categoria_id']           ?? 0),
            'proveedor_id' => (int)($_GET['proveedor_id']           ?? 0),
            'bajo_stock'   => !empty($_GET['bajo_stock']),
            'estado'       => $_GET['estado']                       ?? '',
            'order'        => $_GET['order']                        ?? 'nombre',
            'dir'          => $_GET['dir']                          ?? 'ASC',
            'limit'        => (int)($_GET['limit']                  ?? 25),
            'offset'       => ((int)($_GET['page'] ?? 1) - 1) * (int)($_GET['limit'] ?? 25),
        ];

        $resultado     = $this->productModel->getAll($filters);
        $categorias    = $this->categoryModel->getForSelect();
        $proveedores   = $this->supplierModel->getForSelect();
        $statsInventario = $this->productModel->getStats();
        $bajoStock     = $this->productModel->getLowStock();

        // Calcular paginación
        $totalPaginas  = (int)ceil($resultado['total'] / $filters['limit']);
        $paginaActual  = (int)($_GET['page'] ?? 1);

        $this->render('products/index', [
            'titulo'         => 'Catálogo de Productos',
            'productos'      => $resultado['data'],
            'total'          => $resultado['total'],
            'categorias'     => $categorias,
            'proveedores'    => $proveedores,
            'stats'          => $statsInventario,
            'bajoStock'      => $bajoStock,
            'filtros'        => $filters,
            'totalPaginas'   => $totalPaginas,
            'paginaActual'   => $paginaActual,
        ]);
    }

    // =========================================================================
    // CREAR PRODUCTO
    // =========================================================================

    /**
     * Muestra el formulario de creación de producto
     * GET /productos/crear
     */
    public function create(): void
    {
        $this->requireAuth();

        $this->render('products/form', [
            'titulo'       => 'Nuevo Producto',
            'producto'     => null,
            'categorias'   => $this->categoryModel->getForSelect(),
            'proveedores'  => $this->supplierModel->getForSelect(),
            'unidades'     => $this->getUnidadesMedida(),
            'marcas'       => $this->getMarcas(),
            'errors'       => [],
            'modo'         => 'crear',
        ]);
    }

    /**
     * Procesa y guarda el nuevo producto
     * POST /productos/crear
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $data   = $this->getProductDataFromPost();
        $errors = $this->validateProduct($data);

        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $imagenResult = $this->uploadImage($_FILES['imagen']);
            if ($imagenResult['success']) {
                $data['imagen_path'] = $imagenResult['path'];
            } else {
                $errors['imagen_path'] = $imagenResult['message'];
            }
        }

        if (!empty($errors)) {
            $this->render('products/form', [
                'titulo'      => 'Nuevo Producto',
                'producto'    => $data,
                'categorias'  => $this->categoryModel->getForSelect(),
                'proveedores' => $this->supplierModel->getForSelect(),
                'unidades'    => $this->getUnidadesMedida(),
                'marcas'      => $this->getMarcas(),
                'errors'      => $errors,
                'modo'        => 'crear',
            ]);
            return;
        }

        try {
            // El movimiento inicial es el único responsable de sumar el stock.
            // Así evitamos registrar y sumar dos veces el valor ingresado.
            $stockInicial = (float)($data['stock_actual'] ?? 0);
            $data['stock_actual'] = 0;
            $id = $this->productModel->create($data);

            // Registrar movimiento de stock inicial si hay stock
            if ($stockInicial > 0) {
                $this->productModel->updateStock(
                    $id,
                    $stockInicial,
                    'entrada',
                    'stock_inicial',
                    null,
                    (float)($data['precio_compra_pen'] ?? 0),
                    $_SESSION['user_id'] ?? 1,
                    'Stock inicial del producto'
                );
            }

            $this->setFlash('success', 'Producto creado correctamente.');
            $this->redirect('/productos');

        } catch (RuntimeException $e) {
            $this->setFlash('error', 'Error al crear el producto: ' . $e->getMessage());
            $this->redirect('/productos/crear');
        }
    }

    // =========================================================================
    // VER / EDITAR PRODUCTO
    // =========================================================================

    /**
     * Muestra el detalle de un producto
     * GET /productos/{id}
     */
    public function show(int $id): void
    {
        $this->requireAuth();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/productos');
        }

        $movimientos = $this->productModel->getMovimientos($id, 10);

        $this->render('products/show', [
            'titulo'     => $producto['nombre'],
            'producto'   => $producto,
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * Muestra el formulario de edición
     * GET /productos/{id}/editar
     */
    public function edit(int $id): void
    {
        $this->requireAuth();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/productos');
        }

        $this->render('products/form', [
            'titulo'      => 'Editar Producto: ' . $producto['nombre'],
            'producto'    => $producto,
            'categorias'  => $this->categoryModel->getForSelect(),
            'proveedores' => $this->supplierModel->getForSelect(),
            'unidades'    => $this->getUnidadesMedida(),
            'marcas'      => $this->getMarcas(),
            'errors'      => [],
            'modo'        => 'editar',
        ]);
    }

    /**
     * Procesa y actualiza el producto
     * POST /productos/{id}/editar
     */
public function update(int $id): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $nombre = trim($_POST['nombre'] ?? '');

        // 1. Leer los precios con los nombres exactos de tu formulario (name="precio_venta_pen")
        $precioVenta  = (float)($_POST['precio_venta_pen'] ?? $_POST['precio_venta'] ?? 0);
        $precioCompra = (float)($_POST['precio_compra_pen'] ?? $_POST['precio_compra'] ?? 0);
        $precioMayor  = (float)($_POST['precio_mayorista_pen'] ?? $_POST['precio_mayorista'] ?? 0);

        // 2. Leer inventario y stock
        $stockActual  = isset($_POST['stock_actual']) ? (float)$_POST['stock_actual'] : null;
        $stockMinimo  = isset($_POST['stock_minimo']) ? (float)$_POST['stock_minimo'] : 1;
        $stockMaximo  = isset($_POST['stock_maximo']) ? (float)$_POST['stock_maximo'] : null;

        // 3. Selectores y relaciones
        $categoriaId  = !empty($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : null;
        $marcaId      = !empty($_POST['marca_id']) ? (int)$_POST['marca_id'] : null;
        $unidadId     = !empty($_POST['unidad_medida_id']) ? (int)$_POST['unidad_medida_id'] : null;
        $proveedorId  = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;

        // Si hay stock disponible, asegurar que el producto quede activo
        $activo = isset($_POST['activo']) ? 1 : ($stockActual > 0 ? 1 : 0);

        $data = [
            'nombre'                 => $nombre,
            'precio_venta_pen'       => $precioVenta,
            'precio_compra_pen'      => $precioCompra,
            'precio_mayorista_pen'   => $precioMayor,
            'categoria_id'           => $categoriaId,
            'marca_id'               => $marcaId,
            'unidad_medida_id'       => $unidadId,
            'proveedor_principal_id' => $proveedorId,
            'stock_minimo'           => $stockMinimo,
            'activo'                 => $activo,
        ];

        if ($stockActual !== null) {
            $data['stock_actual'] = $stockActual;
        }
        if ($stockMaximo !== null) {
            $data['stock_maximo'] = $stockMaximo;
        }

        // 4. Subida de imagen si se seleccionó un archivo nuevo
        if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['imagen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $filename = 'prod_' . uniqid('', true) . '.' . $ext;
                $uploadDir = ROOT_PATH . '/public/uploads/products';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $data['imagen_path'] = $filename;
                }
            }
        }

        try {
            $this->productModel->update($id, $data);

            // Si se repuso stock, limpiar la alerta de la campanita
            if ($stockActual !== null && $stockActual > $stockMinimo) {
                $stmtAlerta = $this->db->prepare("UPDATE stock_alertas SET leida = 1 WHERE producto_id = :id");
                $stmtAlerta->execute([':id' => $id]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Producto y precios actualizados correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()];
        }

        redirect('/productos');
    }

    /**
     * Elimina un producto (soft delete)
     * POST /productos/{id}/eliminar
     */
    public function delete(int $id): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/productos');
        }

        $this->productModel->delete($id);
        $this->setFlash('success', 'Producto eliminado correctamente.');
        $this->redirect('/productos');
    }

    // =========================================================================
    // CÓDIGOS DE BARRAS
    // =========================================================================

    /**
     * Genera e imprime el código de barras de un producto
     * GET /productos/{id}/codigo-barras
     */
    public function barcode(int $id): void
    {
        $this->requireAuth();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/productos');
        }

        $tipo   = $_GET['tipo']   ?? 'C128';
        $copias = max(1, (int)($_GET['copias'] ?? 1));

        $codigo = ($tipo === 'EAN13')
            ? ($producto['codigo_barras'] ?: $this->barcodeService->generateEAN13FromInternal($producto['codigo_interno']))
            : $producto['codigo_interno'];

        $imgBase64 = $this->barcodeService->generate($codigo, $tipo);

        $this->render('products/barcode', [
            'titulo'    => 'Código de Barras: ' . $producto['nombre'],
            'producto'  => $producto,
            'codigo'    => $codigo,
            'tipo'      => $tipo,
            'copias'    => $copias,
            'imgBase64' => $imgBase64,
        ]);
    }

    /**
     * Genera una hoja de códigos de barras para múltiples productos
     * POST /productos/codigos-barras-hoja
     */
    public function barcodeSheet(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $ids     = $_POST['ids']     ?? [];
        $formato = $_POST['formato'] ?? '2x5';
        $tamanio = $_POST['tamanio'] ?? 'A4';
        $copias  = max(1, (int)($_POST['copias'] ?? 1));
        $tipo    = $_POST['tipo']    ?? 'C128';

        if (empty($ids)) {
            $this->setFlash('error', 'Seleccione al menos un producto.');
            $this->redirect('/productos');
        }

        $productos = [];
        foreach ($ids as $id) {
            $prod = $this->productModel->getById((int)$id);
            if ($prod) {
                $codigo = ($tipo === 'EAN13' && !empty($prod['codigo_barras']))
                    ? $prod['codigo_barras']
                    : $prod['codigo_interno'];

                $productos[] = [
                    'nombre'       => $prod['nombre'],
                    'codigo'       => $codigo,
                    'precio_venta' => $prod['precio_venta'],
                    'tipo_codigo'  => $tipo,
                ];
            }
        }

        // Generar HTML de hoja de etiquetas
        $html = $this->barcodeService->printSheet($productos, $formato, $tamanio, $copias);

        // Enviar directamente para abrir en nueva ventana
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    // =========================================================================
    // BÚSQUEDA AJAX
    // =========================================================================

    /**
     * Búsqueda AJAX de productos para POS y formularios
     * GET /productos/buscar?q=término
     */
    public function ajaxSearch(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $search  = $this->sanitize($_GET['q'] ?? '');
        $limit   = min(20, (int)($_GET['limit'] ?? 10));

        if (strlen($search) < 1) {
            echo json_encode(['data' => []]);
            exit;
        }

        $productos = $this->productModel->getForPOS($search, $limit);

        echo json_encode([
            'data'  => $productos,
            'total' => count($productos),
        ]);
        exit;
    }

    // =========================================================================
    // MOVIMIENTOS DE STOCK
    // =========================================================================

    /**
     * Muestra el historial de movimientos de stock de un producto
     * GET /productos/{id}/movimientos
     */
    public function stockMovements(int $id): void
    {
        $this->requireAuth();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/productos');
        }

        $limite      = min(200, (int)($_GET['limit'] ?? 50));
        $movimientos = $this->productModel->getMovimientos($id, $limite);

        // Preparar datos para gráfico de Chart.js
        $chartData = $this->prepareChartData($movimientos);

        $this->render('products/stock_movements', [
            'titulo'      => 'Movimientos de Stock: ' . $producto['nombre'],
            'producto'    => $producto,
            'movimientos' => $movimientos,
            'chartData'   => $chartData,
        ]);
    }

    /**
     * Procesa un ajuste manual de stock
     * POST /productos/{id}/ajustar-stock
     */
    public function stockAdjust(int $id): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        $producto = $this->productModel->getById($id);
        if (!$producto) {
            $this->jsonResponse(['success' => false, 'message' => 'Producto no encontrado.']);
            return;
        }

        $stockNuevo = (float)($_POST['stock_nuevo']  ?? 0);
        $motivo     = $this->sanitize($_POST['motivo'] ?? '');
        $tipo       = $_POST['tipo'] ?? 'ajuste';

        if (empty($motivo)) {
            $this->jsonResponse(['success' => false, 'message' => 'El motivo del ajuste es obligatorio.']);
            return;
        }

        $stockActual = (float)$producto['stock_actual'];
        $diferencia  = $stockNuevo - $stockActual;

        try {
            $this->productModel->updateStock(
                $id,
                $diferencia,
                'ajuste',
                'ajuste_manual',
                null,
                (float)$producto['costo_promedio'],
                $_SESSION['user_id'] ?? 1,
                $motivo
            );

            $this->jsonResponse([
                'success'      => true,
                'message'      => 'Stock ajustado correctamente.',
                'stock_nuevo'  => $stockNuevo,
                'stock_previo' => $stockActual,
            ]);

        } catch (RuntimeException $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // IMPORTAR / EXPORTAR EXCEL
    // =========================================================================

    /**
     * Importa productos desde un archivo Excel
     * POST /productos/importar-excel
     */
    public function importExcel(): void
    {
        $this->requireAuth();
        $this->requireCsrf();

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $this->setFlash('error', 'Debe subir un archivo Excel (.xlsx o .xls).');
            $this->redirect('/productos');
        }

        $archivo = $_FILES['archivo'];
        $ext     = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $this->setFlash('error', 'Formato de archivo no válido. Use .xlsx, .xls o .csv');
            $this->redirect('/productos');
        }

        try {
            $reader     = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($archivo['tmp_name']);
            $spreadsheet = $reader->load($archivo['tmp_name']);
            $hoja       = $spreadsheet->getActiveSheet();
            $filas      = $hoja->toArray();

            // La primera fila es el encabezado
            $encabezados = array_shift($filas);

            $importados = 0;
            $errores    = [];

            foreach ($filas as $numFila => $fila) {
                // Omitir filas vacías
                if (empty(array_filter($fila))) {
                    continue;
                }

                try {
                    $dataProducto = [
                        'nombre'         => trim($fila[0] ?? ''),
                        'descripcion'    => trim($fila[1] ?? ''),
                        'categoria_id'   => $this->getCategoriaIdByNombre(trim($fila[2] ?? '')),
                        'codigo_barras'  => trim($fila[3] ?? ''),
                        'precio_compra'  => (float)str_replace(',', '.', $fila[4] ?? 0),
                        'precio_venta'   => (float)str_replace(',', '.', $fila[5] ?? 0),
                        'stock_actual'   => (float)str_replace(',', '.', $fila[6] ?? 0),
                        'stock_minimo'   => (float)str_replace(',', '.', $fila[7] ?? 0),
                        'aplica_igv'     => 1,
                    ];

                    if (empty($dataProducto['nombre'])) {
                        $errores[] = "Fila " . ($numFila + 2) . ": Nombre vacío, omitida.";
                        continue;
                    }

                    $this->productModel->create($dataProducto);
                    $importados++;

                } catch (\Exception $e) {
                    $errores[] = "Fila " . ($numFila + 2) . ": " . $e->getMessage();
                }
            }

            $mensaje = "Importación completada: {$importados} producto(s) importado(s).";
            if (!empty($errores)) {
                $mensaje .= " Errores: " . implode('; ', array_slice($errores, 0, 5));
            }

            $this->setFlash('success', $mensaje);

        } catch (\Exception $e) {
            $this->setFlash('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }

        $this->redirect('/productos');
    }

    /**
     * Exporta el catálogo de productos a Excel
     * GET /productos/exportar-excel
     */
    public function exportExcel(): void
    {
        $this->requireAuth();

        // Accept both GET and POST parameters
        $categoriaId = (int)($_POST['categoria_id'] ?? $_GET['categoria_id'] ?? 0);
        
        // Handle selected_ids from both POST (array) and GET (comma-separated string)
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (empty($selectedIds) && !empty($_GET['selected_ids'])) {
            $selectedIds = explode(',', $_GET['selected_ids']);
        }
        
        $estado      = $_POST['estado'] ?? $_GET['estado'] ?? '';

        if (!empty($selectedIds) && is_array($selectedIds)) {
            // Export only selected products
            $productos = [];
            foreach ($selectedIds as $id) {
                $prod = $this->productModel->getById((int)$id);
                if ($prod) $productos[] = $prod;
            }
        } else {
            $filters   = [
                'categoria_id' => $categoriaId,
                'estado'       => $estado,
                'limit'        => 9999,
                'offset'       => 0,
            ];

            $resultado = $this->productModel->getAll($filters);
            $productos = $resultado['data'];
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $hoja        = $spreadsheet->getActiveSheet();
        $hoja->setTitle('Catálogo de Productos');

        // Encabezados con estilo
        $encabezados = [
            'Código', 'Código Barras', 'Nombre', 'Descripción', 'Categoría',
            'Marca', 'Unidad', 'Proveedor',
            'P. Compra S/', 'P. Venta S/', 'P. Venta USD', 'P. Mayorista S/',
            'Stock Actual', 'Stock Mínimo', 'Stock Máximo',
            'Aplica IGV', 'Tipo Afectación', 'Estado',
        ];

        $col = 'A';
        foreach ($encabezados as $enc) {
            $hoja->setCellValue($col . '1', $enc);
            $hoja->getStyle($col . '1')->getFont()->setBold(true);
            $hoja->getStyle($col . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('1E40AF');
            $hoja->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }

        // Datos de productos
        $fila = 2;
        foreach ($productos as $p) {
            $hoja->setCellValue('A' . $fila, $p['codigo_interno']);
            $hoja->setCellValue('B' . $fila, $p['codigo_barras']         ?? '');
            $hoja->setCellValue('C' . $fila, $p['nombre']);
            $hoja->setCellValue('D' . $fila, $p['descripcion']           ?? '');
            $hoja->setCellValue('E' . $fila, $p['categoria_nombre']      ?? '');
            $hoja->setCellValue('F' . $fila, $p['marca_nombre']          ?? '');
            $hoja->setCellValue('G' . $fila, $p['unidad_abreviatura']    ?? '');
            $hoja->setCellValue('H' . $fila, $p['proveedor_nombre']      ?? '');
            $hoja->setCellValue('I' . $fila, $p['precio_compra_pen']);
            $hoja->setCellValue('J' . $fila, $p['precio_venta_pen']);
            $hoja->setCellValue('K' . $fila, $p['precio_venta_usd']      ?? '');
            $hoja->setCellValue('L' . $fila, $p['precio_mayorista_pen']  ?? '');
            $hoja->setCellValue('M' . $fila, $p['stock_actual']);
            $hoja->setCellValue('N' . $fila, $p['stock_minimo']);
            $hoja->setCellValue('O' . $fila, $p['stock_maximo']          ?? '');
            $hoja->setCellValue('P' . $fila, $p['aplica_igv'] ? 'Sí' : 'No');
            $hoja->setCellValue('Q' . $fila, $p['tipo_afectacion_igv']   ?? '');
            $hoja->setCellValue('R' . $fila, $p['activo'] ? 'Activo' : 'Inactivo');

            // Resaltar stock bajo en rojo
            if ((float)$p['stock_actual'] <= (float)$p['stock_minimo'] && (float)$p['stock_minimo'] > 0) {
                $hoja->getStyle("M{$fila}")->getFont()->getColor()->setRGB('DC2626');
                $hoja->getStyle("M{$fila}")->getFont()->setBold(true);
            }

            $fila++;
        }

        // Autoajustar ancho de columnas
        foreach (range('A', 'R') as $col) {
            $hoja->getColumnDimension($col)->setAutoSize(true);
        }

        $nombreArchivo = 'catalogo_productos_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$nombreArchivo}\"");
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // =========================================================================
    // EXPORTAR PDF
    // =========================================================================

    /**
     * Exporta productos a PDF profesional
     * POST /productos/exportar-pdf
     */
    public function exportPDF(): void
    {
        $this->requireAuth();

        $categoriaId = (int)($_POST['categoria_id'] ?? $_GET['categoria_id'] ?? 0);
        
        // Handle selected_ids from both POST (array) and GET (comma-separated string)
        $selectedIds = $_POST['selected_ids'] ?? [];
        if (empty($selectedIds) && !empty($_GET['selected_ids'])) {
            $selectedIds = explode(',', $_GET['selected_ids']);
        }

        if (!empty($selectedIds) && is_array($selectedIds)) {
            $productos = [];
            foreach ($selectedIds as $id) {
                $prod = $this->productModel->getById((int)$id);
                if ($prod) $productos[] = $prod;
            }
        } else {
            $filters   = [
                'categoria_id' => $categoriaId,
                'limit'        => 9999,
                'offset'       => 0,
            ];
            $resultado = $this->productModel->getAll($filters);
            $productos = $resultado['data'];
        }

        $categoriaNombre = 'Todos';
        if ($categoriaId > 0) {
            $cat = $this->categoryModel->getById($categoriaId);
            $categoriaNombre = $cat['nombre'] ?? "Categoría #{$categoriaId}";
        }

        // Generar HTML para DOMPDF
        $html = $this->generatePDFHtml($productos, $categoriaNombre);

        // Generar PDF con DOMPDF
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'defaultFont'          => 'Helvetica',
        ]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $nombreArchivo = 'productos_' . date('Ymd_His') . '.pdf';

        $dompdf->stream($nombreArchivo, ['Attachment' => true]);
        exit;
    }

    /**
     * Genera el HTML para el PDF de productos
     */
    private function generatePDFHtml(array $productos, string $categoriaNombre): string
    {
        $appName  = defined('APP_NAME') ? APP_NAME : 'FactuPucallpa';
        $dateTime = date('d/m/Y H:i');
        $logoPath = __DIR__ . '/../../public/uploads/logos/logo.png';
        $logoBase64 = '';

        if (file_exists($logoPath) && filesize($logoPath) > 0) {
            $imgData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = "data:image/png;base64,{$imgData}";
        }

        $rows = '';
        $totalValor = 0;
        $totalProductos = count($productos);

        foreach ($productos as $i => $p) {
            $num     = $i + 1;
            $nombre  = htmlspecialchars($p['nombre'] ?? '');
            $codigo  = htmlspecialchars($p['codigo_interno'] ?? '');
            $barras  = htmlspecialchars($p['codigo_barras'] ?? '—');
            $cat     = htmlspecialchars($p['categoria_nombre'] ?? '—');
            $compra  = number_format((float)($p['precio_compra_pen'] ?? 0), 2);
            $venta   = number_format((float)($p['precio_venta_pen'] ?? 0), 2);
            $stock   = (float)($p['stock_actual'] ?? 0);
            $minimo  = (float)($p['stock_minimo'] ?? 0);
            $unidad  = htmlspecialchars($p['unidad_abreviatura'] ?? 'UND');
            $valor   = $stock * (float)($p['precio_compra_pen'] ?? 0);
            $totalValor += $valor;

            $stockClass = $stock <= $minimo && $minimo > 0 ? 'color: #dc2626; font-weight: bold;' : '';

            $rows .= <<<ROW
            <tr>
                <td style="text-align: center; padding: 6px 4px;">{$num}</td>
                <td style="padding: 6px 4px;">{$codigo}</td>
                <td style="padding: 6px 4px; font-weight: 600;">{$nombre}</td>
                <td style="text-align: center; padding: 6px 4px;">{$cat}</td>
                <td style="text-align: right; padding: 6px 4px;">S/ {$compra}</td>
                <td style="text-align: right; padding: 6px 4px; color: #059669; font-weight: 600;">S/ {$venta}</td>
                <td style="text-align: center; padding: 6px 4px; {$stockClass}">{$stock} {$unidad}</td>
                <td style="text-align: right; padding: 6px 4px;">S/ {$valor}</td>
            </tr>
ROW;
        }

        $totalValorFormatted = number_format($totalValor, 2);

        $logoHtml = $logoBase64 ? "<img src=\"{$logoBase64}\" style=\"height: 50px; margin-bottom: 10px;\">" : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catálogo de Productos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9pt; color: #1e293b; padding: 15px 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 3px solid #0ea5e9; }
        .header-left h1 { font-size: 18pt; color: #0f172a; margin-bottom: 3px; }
        .header-left p { font-size: 8pt; color: #64748b; }
        .header-right { text-align: right; font-size: 8pt; color: #64748b; }
        .header-right strong { color: #1e293b; }
        .summary { display: flex; gap: 20px; margin-bottom: 12px; padding: 10px 12px; background: #f8fafc; border-radius: 6px; font-size: 8pt; }
        .summary-item { flex: 1; }
        .summary-item span { color: #64748b; }
        .summary-item strong { font-size: 10pt; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #0ea5e9; color: #ffffff; padding: 8px 4px; font-size: 7.5pt; text-align: left; text-transform: uppercase; letter-spacing: 0.5px; }
        thead th:first-child { border-radius: 4px 0 0 0; }
        thead th:last-child { border-radius: 0 4px 0 0; }
        tbody td { border-bottom: 1px solid #e2e8f0; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #f1f5f9; }
        .footer { margin-top: 15px; padding-top: 8px; border-top: 2px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 7.5pt; color: #64748b; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .page-number { text-align: center; font-size: 7pt; color: #94a3b8; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            {$logoHtml}
            <h1>Catálogo de Productos</h1>
            <p>Reporte generado el {$dateTime} · {$categoriaNombre}</p>
        </div>
        <div class="header-right">
            <strong>{$appName}</strong><br>
            Total de productos: {$totalProductos}
        </div>
    </div>

    <div class="summary">
        <div class="summary-item">
            <span>Productos</span><br>
            <strong>{$totalProductos}</strong>
        </div>
        <div class="summary-item">
            <span>Valor Inventario</span><br>
            <strong>S/ {$totalValorFormatted}</strong>
        </div>
        <div class="summary-item">
            <span>Categoría</span><br>
            <strong>{$categoriaNombre}</strong>
        </div>
        <div class="summary-item">
            <span>Generado</span><br>
            <strong>{$dateTime}</strong>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">#</th>
                <th style="width: 70px;">Código</th>
                <th>Producto</th>
                <th style="width: 70px; text-align: center;">Categoría</th>
                <th style="width: 65px; text-align: right;">P. Compra</th>
                <th style="width: 65px; text-align: right;">P. Venta</th>
                <th style="width: 60px; text-align: center;">Stock</th>
                <th style="width: 65px; text-align: right;">Valor S/</th>
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>

    <div class="footer">
        <span>{$appName} — Sistema de Facturación</span>
        <span>Página 1 de 1</span>
    </div>
</body>
</html>
HTML;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS AUXILIARES
    // =========================================================================

    /**
     * Obtiene los datos del producto desde POST con sanitización
     */
    private function getProductDataFromPost(): array
    {
        return [
            'codigo_barras'       => $this->sanitize($_POST['codigo_barras']       ?? ''),
            'nombre'              => $this->sanitize($_POST['nombre']              ?? ''),
            'descripcion'         => $this->sanitize($_POST['descripcion']         ?? ''),
            'categoria_id'        => (int)($_POST['categoria_id']                  ?? 0) ?: null,
            'marca_id'            => (int)($_POST['marca_id']                      ?? 0) ?: null,
            'unidad_medida_id'       => (int)($_POST['unidad_medida_id']              ?? 0) ?: null,
            'proveedor_principal_id' => (int)($_POST['proveedor_principal_id']        ?? 0) ?: null,
            'precio_compra_pen'      => (float)str_replace(',', '.', $_POST['precio_compra_pen']       ?? '0'),
            'precio_compra_usd'      => (float)str_replace(',', '.', $_POST['precio_compra_usd']   ?? '0'),
            'precio_venta_pen'       => (float)str_replace(',', '.', $_POST['precio_venta_pen']        ?? '0'),
            'precio_venta_usd'       => (float)str_replace(',', '.', $_POST['precio_venta_usd']    ?? '0'),
            'precio_mayorista_pen'   => (float)str_replace(',', '.', $_POST['precio_mayorista_pen']    ?? '0'),
            'stock_actual'           => (float)str_replace(',', '.', $_POST['stock_actual']        ?? '0'),
            'stock_minimo'           => (float)str_replace(',', '.', $_POST['stock_minimo']        ?? '0'),
            'stock_maximo'           => (float)str_replace(',', '.', $_POST['stock_maximo']        ?? '0'),
            'aplica_igv'          => isset($_POST['aplica_igv']) ? 1 : 0,
            'tipo_afectacion_igv' => $this->sanitize($_POST['tipo_afectacion_igv']              ?? '10'),
            'activo'              => isset($_POST['activo']) ? 1 : 0,
        ];
    }

    /**
     * Valida los datos del producto
     *
     * @param array    $data Datos del producto
     * @param int|null $id   ID del producto (para validaciones de edición)
     * @return array Lista de errores de validación
     */
    private function validateProduct(array $data, ?int $id = null): array
    {
        $errors = [];

        if (empty(trim($data['nombre']))) {
            $errors['nombre'] = 'El nombre del producto es obligatorio.';
        } elseif (strlen($data['nombre']) > 255) {
            $errors['nombre'] = 'El nombre no puede superar 255 caracteres.';
        }

        if ($data['precio_venta_pen'] <= 0) {
            $errors['precio_venta_pen'] = 'El precio de venta debe ser mayor a 0.';
        }

        if ($data['precio_compra_pen'] < 0) {
            $errors['precio_compra_pen'] = 'El precio de compra no puede ser negativo.';
        }

        if ($data['stock_minimo'] < 0) {
            $errors['stock_minimo'] = 'El stock mínimo no puede ser negativo.';
        }

        // Validar código de barras si se proporcionó
        if (!empty($data['codigo_barras'])) {
            // Si parece EAN-13 (13 dígitos), validar dígito verificador
            if (preg_match('/^\d{13}$/', $data['codigo_barras'])) {
                $bs = new BarcodeService();
                if (!$bs->validateEAN13($data['codigo_barras'])) {
                    $errors['codigo_barras'] = 'El código EAN-13 no es válido (dígito verificador incorrecto).';
                }
            }
        }

        return $errors;
    }

    /**
     * Sube una imagen de producto al servidor
     *
     * @param array $file $_FILES['imagen']
     * @return array ['success' => bool, 'path' => string|null, 'message' => string]
     */
    private function uploadImage(array $file): array
    {
        $maxSize    = (int)(getenv('UPLOAD_MAX_SIZE') ?: 5242880); // 5MB
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $uploadDir  = __DIR__ . '/../../public/uploads/products/';
        $backupDir  = __DIR__ . '/../../storage/uploads/products/';

        if ($file['size'] > $maxSize) {
            return ['success' => false, 'path' => null, 'message' => 'La imagen supera el tamaño máximo permitido (5MB).'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes)) {
            return ['success' => false, 'path' => null, 'message' => 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP.'];
        }

        foreach ([$uploadDir, $backupDir] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                return ['success' => false, 'path' => null, 'message' => 'No se pudo preparar el directorio de imágenes.'];
            }
            if (!is_writable($directory)) {
                return ['success' => false, 'path' => null, 'message' => 'El directorio de imágenes no tiene permisos de escritura.'];
            }
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $nombreFile = 'prod_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destino    = $uploadDir . $nombreFile;

        if (!move_uploaded_file($file['tmp_name'], $destino)) {
            return ['success' => false, 'path' => null, 'message' => 'Error al guardar la imagen.'];
        }

        // La copia fuera del directorio público permite recuperar las imágenes
        // si una limpieza, despliegue o sincronización elimina los archivos web.
        if (!is_file($destino) || filesize($destino) === 0 || !copy($destino, $backupDir . $nombreFile)) {
            @unlink($destino);
            return ['success' => false, 'path' => null, 'message' => 'No se pudo verificar el respaldo de la imagen.'];
        }

        return ['success' => true, 'path' => $nombreFile, 'message' => ''];
    }

    /**
     * Prepara los datos del historial de movimientos para el gráfico Chart.js
     */
    private function prepareChartData(array $movimientos): array
    {
        $labels = [];
        $stocks = [];

        // Invertir para mostrar cronológicamente (más antiguo primero)
        $movimientosOrdenados = array_reverse($movimientos);

        foreach ($movimientosOrdenados as $mov) {
            $labels[] = date('d/m H:i', strtotime($mov['created_at']));
            $stocks[] = (float)$mov['stock_nuevo'];
        }

        return [
            'labels' => $labels,
            'stocks' => $stocks,
        ];
    }

    /**
     * Obtiene el ID de una categoría por nombre (para importación Excel)
     */
    private function getCategoriaIdByNombre(string $nombre): ?int
    {
        if (empty($nombre)) {
            return null;
        }

        $sql  = 'SELECT id FROM categorias WHERE nombre = :nombre AND eliminado = 0 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }

    /**
     * Obtiene las unidades de medida disponibles
     */
    private function getUnidadesMedida(): array
    {
        $sql  = 'SELECT * FROM unidades_medida ORDER BY nombre ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las marcas disponibles
     */
    private function getMarcas(): array
    {
        $sql  = 'SELECT * FROM marcas WHERE activo = 1 ORDER BY nombre ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // HELPERS DE CONTROLADOR BASE
    // =========================================================================

    private function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    private function requireCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? $_POST['_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token !== ($_SESSION['_csrf_token'] ?? '')) {
            http_response_code(403);
            die('Token CSRF inválido.');
        }
    }

    private function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(500);
            die("Vista no encontrada: {$viewPath}");
        }

        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        include VIEWS_PATH . '/layouts/app.php';
    }

    private function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function jsonResponse(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }
}
