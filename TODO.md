# Correcciones realizadas - COMPLETADO ✅

## 5. Error SUNAT: "No se puede leer (parsear) el archivo XML" ✅

**Causa:** El `XmlGeneratorService.php` generaba el XML declarándolo como `ISO-8859-1`, pero los datos reales (razón social, descripciones de ítems, direcciones) provienen de la base de datos en `UTF-8` con tildes, ñ y otros caracteres. Al usar `htmlspecialchars(..., ENT_QUOTES, 'ISO-8859-1')` sobre texto UTF-8, se producían bytes inválidos que corrompían el XML y SUNAT respondía "No se puede leer (parsear) el archivo XML".

**Solución:** Se cambió el generador de XML a **UTF-8** (estándar UBL 2.1 de SUNAT):
- Declaración `new DOMDocument('1.0', 'ISO-8859-1')` → `new DOMDocument('1.0', 'UTF-8')`.
- Todos los `htmlspecialchars(..., ENT_QUOTES, 'ISO-8859-1')` → `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

**Verificación:** `public/test_firma_fix.php` modificado para usar caracteres UTF-8 (Ñ, Á, É, Í, Ó, Ú). El flujo completo genera → firma → verifica correctamente:
- `XML generado (8136 bytes)`, `XML firmado (18869 bytes)`.
- `FIRMA VÁLIDA` (digest y SignatureValue coinciden).
- Declaración `<?xml version="1.0" encoding="UTF-8"?>` y sin caracteres corruptos.

**Archivos modificados:**
- `app/Services/XmlGeneratorService.php` ✅ (cambios de ISO-8859-1 → UTF-8)

---

## 4. Error SUNAT: "El documento electrónico ingresado ha sido alterado" ✅

**Causa:** El `XmlSignerService.php` firmaba manualmente el XML con un digest incorrecto que no cumplía el estándar XMLDSig exigido por SUNAT:
- Calculaba el `DigestValue` sobre el **documento completo** mediante `$doc->C14N()` (incluyendo el nodo donde luego se insertaba la firma), en lugar de usar la transform `enveloped-signature` que excluye la propia firma del contenido protegido.
- Usaba algoritmos `rsa-sha256`/`sha256`, mientras SUNAT exige `rsa-sha1`/`sha1`.
- Al re-serializar el XML, el digest no coincidía con el contenido finalmente enviado → SUNAT detectaba la inconsistencia como "documento alterado".

**Solución:** `app/Services/XmlSignerService.php` reescrito para delegar la firma en el motor XMLDSig probado y oficial de Greenter (`Greenter\XMLSecLibs\Sunat\SignedXml`), que ya está instalado en el proyecto. Esto garantiza:
- Canonicalización XML-C14N.
- Transform `enveloped-signature` (protege el contenido excluyendo la firma).
- Algoritmos `rsa-sha1` / `sha1` como exige SUNAT.
- Colocación correcta del nodo `<ds:Signature>` dentro de `ext:ExtensionContent` con la cadena de certificación completa.

**Verificación:** `public/test_firma_fix.php` genera una factura, la firma y la valida con `SignedXml::verifyXml()`, confirmando:
- `XML-C14N` + `rsa-sha1` + `sha1` + `enveloped-signature`
- `FIRMA VÁLIDA` (digest y SignatureValue coinciden correctamente).

**Archivos modificados:**
- `app/Services/XmlSignerService.php` ✅ (reescrito para usar Greenter SignedXml)

---

## 1. Error: "strict_types declaration must be the very first statement in the script" ✅
**Causa:** BOM (Byte Order Mark) `EF BB BF` oculto antes del `<?php` en varios archivos.

**Archivos corregidos (BOM eliminado):**
- `app/Services/SunatService.php` ✅
- `app/Services/XmlGeneratorService.php` ✅
- `app/Services/XmlSignerService.php` ✅ (BOM + `declare` movido antes del docblock)
- `app/Services/BarcodeService.php` ✅
- `app/Controllers/SunatController.php` ✅
- `app/Views/products/create.php` ✅

## 2. Error: "Unexpected token 'E', EXCEPTION..." en peticiones AJAX ✅
**Causa:** `custom_exception_handler` en `public/index.php` siempre imprimía texto plano "EXCEPTION: ...", rompiendo respuestas JSON de AJAX.

**Corrección aplicada:**
- `public/index.php` ✅ - El handler ahora detecta peticiones AJAX (header `X-Requested-With` o `Accept: application/json`) y responde con JSON válido con código 500.

## 3. Escaneo completo de BOM en todo el proyecto ✅
- Se escanearon todos los archivos PHP en: `app/`, `config/`, `cron/`, `public/`, `database/`, `install/`
- No se encontraron más archivos con BOM.

