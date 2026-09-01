<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de Generación de Códigos de Barras
 * 
 * Genera códigos de barras en formato PNG (base64), SVG y HTML para
 * impresión de etiquetas. Usa picqer/php-barcode-generator si está disponible;
 * en caso contrario, genera SVG manualmente con barras verticales.
 * 
 * Tipos soportados: CODE128, EAN-13, CODE39
 * 
 * @package App\Services
 */
class BarcodeService
{
    /** @var bool Indica si el paquete picqer está disponible */
    private bool $picqerAvailable;

    /** Alto estándar de las barras en píxeles */
    private int $defaultHeight = 60;

    /** Ancho de cada barra mínima en unidades */
    private int $defaultWidth = 2;

    public function __construct()
    {
        $this->picqerAvailable = class_exists('\Picqer\Barcode\BarcodeGenerator');
    }

    // =========================================================================
    // GENERACIÓN PRINCIPAL
    // =========================================================================

    /**
     * Genera una imagen de código de barras en base64 (PNG o SVG)
     *
     * @param string $code   Código a codificar
     * @param string $type   Tipo: 'C128', 'EAN13', 'C39', 'C128A'
     * @param int    $width  Ancho de cada barra mínima
     * @param int    $height Alto de las barras en px
     * @return string Imagen en base64 (data URI: data:image/png;base64,...)
     */
    public function generate(string $code, string $type = 'C128', int $width = 2, int $height = 60): string
    {
        if ($this->picqerAvailable) {
            return $this->generateWithPicqer($code, $type, $width, $height);
        }

        // Fallback: generar SVG manual
        $svg = $this->generateSVG($code, $type, $width, $height);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Genera el HTML de un código de barras para insertar en páginas web
     *
     * @param string $code      Código a codificar
     * @param string $type      Tipo de código de barras
     * @param bool   $showCode  Si se muestra el texto del código debajo
     * @return string HTML con la imagen del código de barras
     */
    public function generateHTML(string $code, string $type = 'C128', bool $showCode = true): string
    {
        $imgSrc = $this->generate($code, $type);
        $codeDisplay = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $html = '<div class="barcode-container" style="text-align:center;display:inline-block;">';
        $html .= '<img src="' . $imgSrc . '" alt="Código de barras: ' . $codeDisplay . '" style="max-width:100%;height:auto;">';

        if ($showCode) {
            $html .= '<div class="barcode-text" style="font-family:monospace;font-size:12px;margin-top:3px;letter-spacing:1px;">';
            $html .= $codeDisplay;
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Genera un código de barras EAN-13
     * Si el código no tiene 13 dígitos, lo completa con el dígito verificador
     *
     * @param string $code Código (12 o 13 dígitos numéricos)
     * @return string Imagen en base64
     */
    public function generateEAN13(string $code): string
    {
        // Asegurarse de que sea un EAN-13 válido
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) === 12) {
            // Calcular y agregar dígito verificador
            $code .= $this->calcularDigitoEAN13($code);
        }

        if (strlen($code) !== 13) {
            // Código inválido, rellenar con ceros
            $code = str_pad(substr($code, 0, 12), 12, '0', STR_PAD_LEFT);
            $code .= $this->calcularDigitoEAN13($code);
        }

        return $this->generate($code, 'EAN13');
    }

    /**
     * Genera un código de barras CODE128 (para códigos internos alfanuméricos)
     *
     * @param string $code Código a codificar
     * @return string Imagen en base64
     */
    public function generateCODE128(string $code): string
    {
        return $this->generate($code, 'C128');
    }

    /**
     * Genera una hoja HTML completa con múltiples etiquetas para impresión
     *
     * @param array  $products   Lista de productos: ['nombre', 'codigo', 'precio_venta', 'tipo_codigo']
     * @param string $formato    '2x5' (2 columnas x 5 filas = 10 por página) o '3x8' (3x8 = 24 por página)
     * @param string $tamanio    'A4', '58mm', '80mm' (para etiquetadora térmica)
     * @param int    $copias     Número de copias de cada etiqueta
     * @return string HTML completo para abrir en nueva ventana e imprimir
     */
    public function printSheet(array $products, string $formato = '2x5', string $tamanio = 'A4', int $copias = 1): string
    {
        // Expandir lista según copias
        $etiquetas = [];
        foreach ($products as $product) {
            for ($i = 0; $i < $copias; $i++) {
                $etiquetas[] = $product;
            }
        }

        // Configurar columnas según formato
        $columnas = match ($formato) {
            '3x8'   => 3,
            '4x10'  => 4,
            default => 2,  // '2x5'
        };

        // Generar CSS según tamaño
        $css = $this->getEtiquetaCSS($tamanio, $columnas);

        // Generar HTML de etiquetas
        $etiquetasHTML = '';
        foreach ($etiquetas as $producto) {
            $nombre  = htmlspecialchars(substr($producto['nombre'] ?? '', 0, 35), ENT_QUOTES, 'UTF-8');
            $codigo  = $producto['codigo']      ?? '';
            $precio  = $producto['precio_venta'] ?? '';
            $tipo    = $producto['tipo_codigo']   ?? 'C128';
            $imgSrc  = $this->generate($codigo, $tipo, 1, 40);

            $precioText = $precio ? 'S/ ' . number_format((float)$precio, 2) : '';

            $etiquetasHTML .= "
            <div class='etiqueta'>
                <div class='etiqueta-nombre'>{$nombre}</div>
                <div class='etiqueta-barcode'>
                    <img src='{$imgSrc}' alt='{$codigo}'>
                </div>
                <div class='etiqueta-codigo'>{$codigo}</div>
                " . ($precioText ? "<div class='etiqueta-precio'>{$precioText}</div>" : '') . "
            </div>";
        }

        return "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Etiquetas de Códigos de Barras</title>
    <style>{$css}</style>
</head>
<body>
    <div class='etiquetas-grid'>
        {$etiquetasHTML}
    </div>
    <div class='no-print' style='text-align:center;margin:20px;'>
        <button onclick='window.print()' style='padding:10px 30px;font-size:16px;cursor:pointer;background:#007bff;color:white;border:none;border-radius:5px;'>
            🖨️ Imprimir Etiquetas
        </button>
        <button onclick='window.close()' style='padding:10px 30px;font-size:16px;cursor:pointer;background:#6c757d;color:white;border:none;border-radius:5px;margin-left:10px;'>
            ✖ Cerrar
        </button>
    </div>
</body>
</html>";
    }

    // =========================================================================
    // VALIDACIÓN EAN-13
    // =========================================================================

    /**
     * Valida el dígito verificador de un código EAN-13
     *
     * @param string $code Código EAN-13 de 13 dígitos
     * @return bool True si el código es válido
     */
    public function validateEAN13(string $code): bool
    {
        if (!preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        $digitoEsperado = $this->calcularDigitoEAN13(substr($code, 0, 12));
        return (int)$code[12] === $digitoEsperado;
    }

    /**
     * Convierte un código interno alfanumérico a un EAN-13 válido
     * Genera un código numérico de 12 dígitos usando el hash del código interno
     * y le agrega el dígito verificador.
     *
     * @param string $internalCode Código interno del producto (ej: INF-2024-0001)
     * @return string Código EAN-13 de 13 dígitos
     */
    public function generateEAN13FromInternal(string $internalCode): string
    {
        // Usar CRC32 del código para generar un número consistente
        // Prefijo 200 (reservado para uso interno según GS1)
        $hash    = abs(crc32($internalCode));
        $base    = '200' . str_pad((string)($hash % 1000000000), 9, '0', STR_PAD_LEFT);
        $base    = substr($base, 0, 12);
        $digito  = $this->calcularDigitoEAN13($base);

        return $base . $digito;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Genera imagen usando la librería picqer/php-barcode-generator
     */
    private function generateWithPicqer(string $code, string $type, int $width, int $height): string
    {
        try {
            /** @var \Picqer\Barcode\BarcodeGeneratorPNG $generator */
            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();

            $picqerType = match (strtoupper($type)) {
                'EAN13'      => \Picqer\Barcode\BarcodeGenerator::TYPE_EAN_13,
                'EAN8'       => \Picqer\Barcode\BarcodeGenerator::TYPE_EAN_8,
                'C39', 'CODE39' => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_39,
                'C128A'      => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128_A,
                'C128B'      => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128_B,
                default      => \Picqer\Barcode\BarcodeGenerator::TYPE_CODE_128, // C128
            };

            $png = $generator->getBarcode($code, $picqerType, $width, $height, [0, 0, 0]);
            return 'data:image/png;base64,' . base64_encode($png);

        } catch (\Exception $e) {
            // Si falla, usar SVG de fallback
            $svg = $this->generateSVG($code, $type, $width, $height);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }
    }

    /**
     * Genera un SVG básico de código de barras (fallback sin dependencias externas)
     * Implementa una versión simplificada del algoritmo CODE128-B
     */
    private function generateSVG(string $code, string $type, int $unitWidth, int $height): string
    {
        // Tabla simplificada de barras CODE128-B (solo ASCII 32-127)
        // Cada patrón es una cadena de bits donde 1=barra, 0=espacio
        $patterns = $this->getCODE128Patterns();

        $bars    = [];
        $quiet   = 10; // Zona de silencio

        // Símbolo de inicio CODE128-B = patrón 104
        $startPattern = '11010010000';
        $this->addPattern($bars, $startPattern, $unitWidth);

        $checksum = 104;
        $pos      = 1;

        foreach (str_split($code) as $char) {
            $val     = ord($char) - 32;
            $checksum += $val * $pos;
            $pos++;
            $pattern = $patterns[$val] ?? $patterns[0];
            $this->addPattern($bars, $pattern, $unitWidth);
        }

        // Dígito verificador
        $checkVal = $checksum % 103;
        $checkPattern = $patterns[$checkVal] ?? $patterns[0];
        $this->addPattern($bars, $checkPattern, $unitWidth);

        // Símbolo de parada
        $stopPattern = '1100011101011';
        $this->addPattern($bars, $stopPattern, $unitWidth);

        // Calcular ancho total del SVG
        $totalWidth = $quiet + array_sum($bars) + $quiet;

        // Generar SVG
        $svgBars = '';
        $x       = $quiet;
        $isBarra = true;

        foreach ($bars as $w) {
            if ($isBarra) {
                $svgBars .= "<rect x='{$x}' y='0' width='{$w}' height='{$height}' fill='black'/>";
            }
            $x      += $w;
            $isBarra = !$isBarra;
        }

        // Texto del código debajo
        $textY = $height + 12;
        $fontSize = min(10, (int)($totalWidth / max(strlen($code), 1) * 1.2));
        $svgText = "<text x='" . ($totalWidth / 2) . "' y='{$textY}' text-anchor='middle' "
                 . "font-family='monospace' font-size='{$fontSize}' fill='black'>"
                 . htmlspecialchars($code, ENT_XML1, 'UTF-8')
                 . "</text>";

        $svgHeight = $height + 20;

        return "<?xml version='1.0' encoding='UTF-8'?>
<svg xmlns='http://www.w3.org/2000/svg' width='{$totalWidth}' height='{$svgHeight}' viewBox='0 0 {$totalWidth} {$svgHeight}'>
    <rect width='{$totalWidth}' height='{$svgHeight}' fill='white'/>
    {$svgBars}
    {$svgText}
</svg>";
    }

    /**
     * Agrega un patrón de bits al array de barras
     */
    private function addPattern(array &$bars, string $pattern, int $unitWidth): void
    {
        $currentBit  = $pattern[0] ?? '1';
        $currentWidth = $unitWidth;

        for ($i = 1, $len = strlen($pattern); $i < $len; $i++) {
            if ($pattern[$i] === $currentBit) {
                $currentWidth += $unitWidth;
            } else {
                $bars[]       = $currentWidth;
                $currentBit   = $pattern[$i];
                $currentWidth  = $unitWidth;
            }
        }

        $bars[] = $currentWidth;
    }

    /**
     * Calcula el dígito verificador de un código EAN-13 de 12 dígitos
     */
    private function calcularDigitoEAN13(string $codigo12): int
    {
        $suma = 0;
        for ($i = 0; $i < 12; $i++) {
            $suma += (int)$codigo12[$i] * (($i % 2 === 0) ? 1 : 3);
        }

        $resto = $suma % 10;
        return $resto === 0 ? 0 : 10 - $resto;
    }

    /**
     * Retorna el CSS para la hoja de etiquetas según el tamaño de papel
     */
    private function getEtiquetaCSS(string $tamanio, int $columnas): string
    {
        $pageSize   = match ($tamanio) {
            '58mm'  => '@page { size: 58mm auto; margin: 2mm; }',
            '80mm'  => '@page { size: 80mm auto; margin: 3mm; }',
            default => '@page { size: A4; margin: 10mm; }',
        };

        $etiWidth = match ($tamanio) {
            '58mm'  => '54mm',
            '80mm'  => '76mm',
            default => match ($columnas) {
                3    => '62mm',
                4    => '46mm',
                default => '95mm',
            },
        };

        return "
            {$pageSize}
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: Arial, sans-serif; background: white; }
            .no-print { padding: 15px; }
            @media print { .no-print { display: none; } }
            .etiquetas-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 2mm;
                padding: 2mm;
            }
            .etiqueta {
                width: {$etiWidth};
                border: 0.5pt solid #ccc;
                padding: 2mm;
                text-align: center;
                page-break-inside: avoid;
                display: flex;
                flex-direction: column;
                align-items: center;
                min-height: 28mm;
            }
            .etiqueta-nombre {
                font-size: 7pt;
                font-weight: bold;
                line-height: 1.2;
                margin-bottom: 1mm;
                max-height: 14pt;
                overflow: hidden;
                width: 100%;
            }
            .etiqueta-barcode img {
                max-width: 100%;
                height: 14mm;
                object-fit: contain;
            }
            .etiqueta-codigo {
                font-size: 6.5pt;
                font-family: monospace;
                letter-spacing: 0.5px;
                margin-top: 0.5mm;
            }
            .etiqueta-precio {
                font-size: 9pt;
                font-weight: bold;
                margin-top: 1mm;
                color: #000;
            }
        ";
    }

    /**
     * Retorna la tabla de patrones CODE128-B para los caracteres ASCII 32-127
     * Cada patrón es una representación binaria de la codificación
     */
    private function getCODE128Patterns(): array
    {
        // Patrones Code128 estándar (módulos: 2-1-1=barra estrecha-ancha-estrecha...)
        // Representados como cadenas de bits donde 1=barra negra, 0=espacio blanco
        return [
            '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
            '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
            '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
            '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
            '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
            '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
            '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
            '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
            '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
            '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
            '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
            '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
            '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
            '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
            '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
            '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
            '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
            '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
            '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
            '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
            '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
            '11010011100',
        ];
    }
}
