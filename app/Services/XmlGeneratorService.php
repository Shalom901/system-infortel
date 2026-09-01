<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;

/**
 * Servicio de Generación de XML UBL 2.1 para SUNAT
 */
class XmlGeneratorService
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CREDIT  = 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2';
    private const NS_DEBIT   = 'urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2';
    private const NS_CAC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    private const NS_EXT     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';
    private const NS_DSIG    = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_SAC     = 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1';

    private const TIPO_FACTURA = '01';
    private const TIPO_BOLETA  = '03';

    private const CUSTOMIZATION_INVOICE = '2.0';
    private const CUSTOMIZATION_NC_ND   = '1.0';

    public function generateFactura(array $venta): string
    {
        return $this->generateInvoice($venta, self::TIPO_FACTURA);
    }

    public function generateBoleta(array $venta): string
    {
        return $this->generateInvoice($venta, self::TIPO_BOLETA);
    }

    // =========================================================================
    // FACTURAS Y BOLETAS
    // =========================================================================
    private function generateInvoice(array $venta, string $tipo): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;

        $root = $doc->createElementNS(self::NS_INVOICE, 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DSIG);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);

        $extensions = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $extension  = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extContent = $doc->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $extension->appendChild($extContent);
        $extensions->appendChild($extension);
        $root->appendChild($extensions);

        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:UBLVersionID', '2.1'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CustomizationID', self::CUSTOMIZATION_INVOICE));

        // 1. DEFINICIÓN DEL TIPO DE OPERACIÓN (Catálogo 51)
        $profileId = $doc->createElementNS(self::NS_CBC, 'cbc:ProfileID', '0101');
        $profileId->setAttribute('schemeName', 'Tipo de Operacion');
        $profileId->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $profileId->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51');
        $root->appendChild($profileId);

        $numeroCorrelativo = str_pad((string)($venta['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
        $idStr = ($venta['serie'] ?? 'F001') . '-' . $numeroCorrelativo;
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $idStr));

        $fechaEmision = date('Y-m-d', strtotime($venta['fecha_emision'] ?? 'now'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueDate', $fechaEmision));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueTime', date('H:i:s')));

        // 1. ATRIBUTOS COMPLETOS DEL TIPO DE DOCUMENTO
        $invoiceTypeCode = $doc->createElementNS(self::NS_CBC, 'cbc:InvoiceTypeCode', $tipo);
        $invoiceTypeCode->setAttribute('listID', '0101'); 
        $invoiceTypeCode->setAttribute('listAgencyName', 'PE:SUNAT');
        $invoiceTypeCode->setAttribute('listName', 'Tipo de Documento');
        $invoiceTypeCode->setAttribute('listURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01');
        $root->appendChild($invoiceTypeCode);

        $moneda = $venta['moneda'] ?? 'PEN';
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DocumentCurrencyCode', $moneda));
        // =================================================================
        // EMISOR
        // =================================================================
        $supplier = $doc->createElementNS(self::NS_CAC, 'cac:AccountingSupplierParty');
        $supplierParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        
        $supplierPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $emisorId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $this->getRucEmisor());
        $emisorId->setAttribute('schemeID', '6');
        $supplierPartyId->appendChild($emisorId);
        $supplierParty->appendChild($supplierPartyId);

        $partyName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $partyName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $supplierParty->appendChild($partyName);

        $partyTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $taxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $taxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $taxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', 'Registro Único de Contribuyentes'));
        $partyTaxScheme->appendChild($taxScheme);
        $supplierParty->appendChild($partyTaxScheme);

        $partyLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity');
        $partyLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        
        // FIX 3030: Dirección debe ir DENTRO de PartyLegalEntity
        $address = $this->createAddress($doc, $this->getDireccionEmisor(), $this->getDistrito(), $this->getProvincia(), $this->getDepartamento(), $this->getUbigeo());
        $partyLegalEntity->appendChild($address);
        $supplierParty->appendChild($partyLegalEntity);

        $supplier->appendChild($supplierParty);
        $root->appendChild($supplier);

        // =================================================================
        // CLIENTE
        // =================================================================
        $customer = $doc->createElementNS(self::NS_CAC, 'cac:AccountingCustomerParty');
        $customerParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');

        $customerPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $tipoDocCliente = $this->getTipoDocCliente($venta);
        $numDocCliente  = $this->getNumDocCliente($venta);

        $customerId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $numDocCliente);
        $customerId->setAttribute('schemeID', (string)$tipoDocCliente);
        $customerPartyId->appendChild($customerId);
        $customerParty->appendChild($customerPartyId);

        $customerName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $customerName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($this->getNombreCliente($venta)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerName);

        if (!empty($venta['cliente_direccion'])) {
            $customerAddress = $this->createAddressSimplified($doc, $venta['cliente_direccion']);
            $customerParty->appendChild($customerAddress);
        }

        $customerTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $ctTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $ctTaxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $ctTaxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', 'Registro Único de Contribuyentes'));
        $customerTaxScheme->appendChild($ctTaxScheme);
        $customerParty->appendChild($customerTaxScheme);

        $customerLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity');
        $customerLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($this->getNombreCliente($venta)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerLegalEntity);

        $customer->appendChild($customerParty);
        $root->appendChild($customer);

        if ($tipo === self::TIPO_FACTURA) {
            $paymentTerms = $doc->createElementNS(self::NS_CAC, 'cac:PaymentTerms');
            $paymentTerms->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', 'FormaPago'));
            $paymentTerms->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:PaymentMeansID', 'Contado'));
            $root->appendChild($paymentTerms);
        }

        // =================================================================
        // PRE-CÁLCULO EXACTO DE TRIBUTOS GLOBALES (FIX ERROR 3275)
        // =================================================================
        $items = $venta['items'] ?? [];
        $totalesImpuestos = [
            '1000' => ['nombre' => 'IGV', 'tax_amount' => 0.0, 'taxable_amount' => 0.0],
            '9997' => ['nombre' => 'EXO', 'tax_amount' => 0.0, 'taxable_amount' => 0.0]
        ];

        $globalIgv = 0.0;
        $globalSubtotal = 0.0;
        $globalTotal = 0.0;

        foreach ($items as &$item) {
            $cantidad = (float)($item['cantidad'] ?? 1);
            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $precioUnitarioConIgv = (float)($item['precio_unitario_pen'] ?? ($valorUnitario * 1.18));
            
            $igvItem = ($precioUnitarioConIgv - $valorUnitario) * $cantidad;
            $subtotalItem = $valorUnitario * $cantidad;
            
            $isExonerated = $igvItem <= 0;
            $taxId = $isExonerated ? '9997' : '1000';
            
            $totalesImpuestos[$taxId]['tax_amount'] += $igvItem;
            $totalesImpuestos[$taxId]['taxable_amount'] += $subtotalItem;
            
            $globalIgv += $igvItem;
            $globalSubtotal += $subtotalItem;
            $globalTotal += ($subtotalItem + $igvItem);
            
            // Inyectamos cálculos exactos para no fallar en la generación de líneas
            $item['_igv'] = $igvItem;
            $item['_subtotal'] = $subtotalItem;
            $item['_tax_id'] = $taxId;
            $item['_tax_name'] = $isExonerated ? 'EXO' : 'IGV';
            $item['_afectacion'] = $isExonerated ? '20' : '10';
            $item['_precio_con_igv'] = $precioUnitarioConIgv;
        }
        unset($item);

        $taxTotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $taxAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($globalIgv, 2, '.', ''));
        $taxAmount->setAttribute('currencyID', $moneda);
        $taxTotal->appendChild($taxAmount);

        foreach ($totalesImpuestos as $tId => $tData) {
            if ($tData['taxable_amount'] > 0 || $tData['tax_amount'] > 0) {
                $taxSubtotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
                
                $taxableAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($tData['taxable_amount'], 2, '.', ''));
                $taxableAmount->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxableAmount);
                
                $taxAmountSub = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($tData['tax_amount'], 2, '.', ''));
                $taxAmountSub->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxAmountSub);
                
                $taxCategoryNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
                $taxSchemeNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
                
                // FIX STRICT TYPING: Cast explícito a (string) para la clave del array
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$tId));
                
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $tData['nombre']));
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
                
                $taxCategoryNode->appendChild($taxSchemeNode);
                $taxSubtotal->appendChild($taxCategoryNode);
                $taxTotal->appendChild($taxSubtotal);
            }
        }
        $root->appendChild($taxTotal);

        // =================================================================
        // TOTALES
        // =================================================================
        $legalMonetary = $doc->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');

        $totalSubtotal = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($globalSubtotal, 2, '.', ''));
        $totalSubtotal->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalSubtotal);

        $totalIgv = $doc->createElementNS(self::NS_CBC, 'cbc:TaxInclusiveAmount', number_format($globalTotal, 2, '.', ''));
        $totalIgv->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalIgv);

        $totalPagar = $doc->createElementNS(self::NS_CBC, 'cbc:PayableAmount', number_format($globalTotal, 2, '.', ''));
        $totalPagar->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalPagar);

        $root->appendChild($legalMonetary);

        // =================================================================
        // DETALLE DE ÍTEMS
        // =================================================================
        $contador = 1;

        foreach ($items as $item) {
            $lineNode = $doc->createElementNS(self::NS_CAC, 'cac:InvoiceLine');
            $lineNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$contador));

            $cantidad = (float)($item['cantidad'] ?? 1);
            $qtyNode = $doc->createElementNS(self::NS_CBC, 'cbc:InvoicedQuantity', number_format($cantidad, 2, '.', ''));
            $qtyNode->setAttribute('unitCode', $this->getUnidadMedida($item));
            $lineNode->appendChild($qtyNode);

            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $lineAmount = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($item['_subtotal'], 2, '.', ''));
            $lineAmount->setAttribute('currencyID', $moneda);
            $lineNode->appendChild($lineAmount);

            $pricingRef = $doc->createElementNS(self::NS_CAC, 'cac:PricingReference');
            $altCondition = $doc->createElementNS(self::NS_CAC, 'cac:AlternativeConditionPrice');
            $altPriceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($item['_precio_con_igv'], 2, '.', ''));
            $altPriceAmount->setAttribute('currencyID', $moneda);
            $altCondition->appendChild($altPriceAmount);
            $altCondition->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:PriceTypeCode', '01'));
            $pricingRef->appendChild($altCondition);
            $lineNode->appendChild($pricingRef);

            $taxTotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
            $taxAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem->setAttribute('currencyID', $moneda);
            $taxTotalItem->appendChild($taxAmountItem);

            $taxSubtotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
            $taxableAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($item['_subtotal'], 2, '.', ''));
            $taxableAmountItem->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxableAmountItem);

            $taxAmountItem2 = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem2->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxAmountItem2);

            $taxCategoryItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Percent', '18.00'));
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxExemptionReasonCode', $item['_afectacion']));

            $taxSchemeItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['_tax_id']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $item['_tax_name']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
            
            $taxCategoryItem->appendChild($taxSchemeItem);
            $taxSubtotalItem->appendChild($taxCategoryItem);
            $taxTotalItem->appendChild($taxSubtotalItem);
            $lineNode->appendChild($taxTotalItem);

            $itemNode = $doc->createElementNS(self::NS_CAC, 'cac:Item');
            $descripcion = htmlspecialchars($this->normalizeText($item['descripcion_item'] ?? $item['descripcion'] ?? 'Producto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $itemNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Description', $descripcion));

            if (!empty($item['codigo_interno'])) {
                $sellersId = $doc->createElementNS(self::NS_CAC, 'cac:SellersItemIdentification');
                $sellersId->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['codigo_interno']));
                $itemNode->appendChild($sellersId);
            }
            $lineNode->appendChild($itemNode);

            $priceNode = $doc->createElementNS(self::NS_CAC, 'cac:Price');
            $priceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($valorUnitario, 2, '.', ''));
            $priceAmount->setAttribute('currencyID', $moneda);
            $priceNode->appendChild($priceAmount);
            $lineNode->appendChild($priceNode);

            $root->appendChild($lineNode);
            $contador++;
        }

        $doc->appendChild($root);
        return $this->finalizeXml($doc);
    }

    // =========================================================================
    // NOTAS DE CRÉDITO
    // =========================================================================
    public function generateNotaCredito(array $nota): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;

        $root = $doc->createElementNS(self::NS_CREDIT, 'CreditNote');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DSIG);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);

        $extensions = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $extension  = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extContent = $doc->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $extension->appendChild($extContent);
        $extensions->appendChild($extension);
        $root->appendChild($extensions);

        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:UBLVersionID', '2.1'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CustomizationID', self::CUSTOMIZATION_NC_ND));

        // Inyección obligatoria para Notas de Crédito y Débito
        $profileId = $doc->createElementNS(self::NS_CBC, 'cbc:ProfileID', '0101');
        $profileId->setAttribute('schemeName', 'Tipo de Operacion');
        $profileId->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $profileId->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51');
        $root->appendChild($profileId);

        $numeroCorrelativo = str_pad((string)($nota['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
        $idStr = ($nota['serie'] ?? 'FC01') . '-' . $numeroCorrelativo;
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $idStr));

        $fechaEmision = date('Y-m-d', strtotime($nota['fecha_emision'] ?? 'now'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueDate', $fechaEmision));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueTime', date('H:i:s')));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CreditNoteTypeCode', '07'));
        
        $moneda = $nota['moneda'] ?? 'PEN';
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DocumentCurrencyCode', $moneda));

        $origen = $nota['venta_origen'] ?? $nota['comprobante_origen'] ?? [];
        if (!empty($origen)) {
            $billingRef = $doc->createElementNS(self::NS_CAC, 'cac:BillingReference');
            $invDocRef  = $doc->createElementNS(self::NS_CAC, 'cac:InvoiceDocumentReference');
            $tipoOrigen = $origen['tipo_comprobante'] ?? '01';
            $serieOrigen = $origen['serie'] ?? 'F001';
            $numOrigen   = str_pad((string)($origen['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
            $invDocRef->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $serieOrigen . '-' . $numOrigen));
            $invDocRef->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DocumentTypeCode', $tipoOrigen));
            $billingRef->appendChild($invDocRef);
            $root->appendChild($billingRef);
        }

        $motivoCodigo = $nota['motivo_codigo'] ?? '01';
        $motivoDesc   = $nota['motivo_descripcion'] ?? 'Anulacion de la operacion';
        $noteNode = $doc->createElementNS(self::NS_CBC, 'cbc:Note', htmlspecialchars('Motivo: ' . $motivoDesc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $noteNode->setAttribute('languageLocaleID', 'es-PE');
        $root->appendChild($noteNode);
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DiscrepancyResponseCode', $motivoCodigo));

        $supplier = $doc->createElementNS(self::NS_CAC, 'cac:AccountingSupplierParty');
        $supplierParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        $supplierPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $emisorId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $this->getRucEmisor());
        $emisorId->setAttribute('schemeID', '6'); 
        $supplierPartyId->appendChild($emisorId);
        $supplierParty->appendChild($supplierPartyId);

        $partyName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $partyName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $supplierParty->appendChild($partyName);

        $partyTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $taxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $taxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $partyTaxScheme->appendChild($taxScheme);
        $supplierParty->appendChild($partyTaxScheme);

        $partyLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity'); 
        $partyLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        
        // FIX 3030 PARA NOTAS DE CRÉDITO
        $address = $this->createAddress($doc, $this->getDireccionEmisor(), $this->getDistrito(), $this->getProvincia(), $this->getDepartamento(), $this->getUbigeo());
        $partyLegalEntity->appendChild($address);
        
        $supplierParty->appendChild($partyLegalEntity);

        $supplier->appendChild($supplierParty);
        $root->appendChild($supplier);

        $customer = $doc->createElementNS(self::NS_CAC, 'cac:AccountingCustomerParty');
        $customerParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        $customerPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $tipoDocCliente = $nota['cliente_tipo_doc'] ?? '6';
        $numDocCliente  = $nota['cliente_numero_doc'] ?? '00000000';
        $customerId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $numDocCliente);
        $customerId->setAttribute('schemeID', (string)(int)$tipoDocCliente);
        $customerPartyId->appendChild($customerId);
        $customerParty->appendChild($customerPartyId);

        $customerName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $customerName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($nota['cliente_nombre'] ?? $nota['razon_social'] ?? 'CLIENTE GENERICO'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerName);

        if (!empty($nota['cliente_direccion'])) {
            $customerParty->appendChild($this->createAddressSimplified($doc, $nota['cliente_direccion']));
        }
        $customerTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $ctTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $ctTaxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $customerTaxScheme->appendChild($ctTaxScheme);
        $customerParty->appendChild($customerTaxScheme);

        $customerLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity'); 
        $customerLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($nota['cliente_nombre'] ?? $nota['razon_social'] ?? 'CLIENTE GENERICO'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerLegalEntity);

        $customer->appendChild($customerParty);
        $root->appendChild($customer);

        // =================================================================
        // PRE-CÁLCULO EXACTO TRIBUTOS GLOBALES
        // =================================================================
        $items = $nota['items'] ?? [];
        $totalesImpuestos = [
            '1000' => ['nombre' => 'IGV', 'tax_amount' => 0.0, 'taxable_amount' => 0.0],
            '9997' => ['nombre' => 'EXO', 'tax_amount' => 0.0, 'taxable_amount' => 0.0]
        ];

        $globalIgv = 0.0;
        $globalSubtotal = 0.0;
        $globalTotal = 0.0;

        foreach ($items as &$item) {
            $cantidad = (float)($item['cantidad'] ?? 1);
            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $precioUnitarioConIgv = (float)($item['precio_unitario_pen'] ?? ($valorUnitario * 1.18));
            
            $igvItem = ($precioUnitarioConIgv - $valorUnitario) * $cantidad;
            $subtotalItem = $valorUnitario * $cantidad;
            
            $isExonerated = $igvItem <= 0;
            $taxId = $isExonerated ? '9997' : '1000';
            
            $totalesImpuestos[$taxId]['tax_amount'] += $igvItem;
            $totalesImpuestos[$taxId]['taxable_amount'] += $subtotalItem;
            
            $globalIgv += $igvItem;
            $globalSubtotal += $subtotalItem;
            $globalTotal += ($subtotalItem + $igvItem);
            
            $item['_igv'] = $igvItem;
            $item['_subtotal'] = $subtotalItem;
            $item['_tax_id'] = $taxId;
            $item['_tax_name'] = $isExonerated ? 'EXO' : 'IGV';
            $item['_afectacion'] = $isExonerated ? '20' : '10';
            $item['_precio_con_igv'] = $precioUnitarioConIgv;
        }
        unset($item);

        $taxTotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $taxAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($globalIgv, 2, '.', ''));
        $taxAmount->setAttribute('currencyID', $moneda);
        $taxTotal->appendChild($taxAmount);

        foreach ($totalesImpuestos as $tId => $tData) {
            if ($tData['taxable_amount'] > 0 || $tData['tax_amount'] > 0) {
                $taxSubtotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
                
                $taxableAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($tData['taxable_amount'], 2, '.', ''));
                $taxableAmount->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxableAmount);
                
                $taxAmountSub = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($tData['tax_amount'], 2, '.', ''));
                $taxAmountSub->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxAmountSub);
                
                $taxCategoryNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
                $taxSchemeNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
                
                // FIX STRICT TYPING: Cast explícito a (string) para la clave del array
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$tId));
                
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $tData['nombre']));
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
                
                $taxCategoryNode->appendChild($taxSchemeNode);
                $taxSubtotal->appendChild($taxCategoryNode);
                $taxTotal->appendChild($taxSubtotal);
            }
        }
        $root->appendChild($taxTotal);

        $legalMonetary = $doc->createElementNS(self::NS_CAC, 'cac:LegalMonetaryTotal');
        $totalSubtotal = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($globalSubtotal, 2, '.', ''));
        $totalSubtotal->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalSubtotal);
        $totalPagar = $doc->createElementNS(self::NS_CBC, 'cbc:PayableAmount', number_format($globalTotal, 2, '.', ''));
        $totalPagar->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalPagar);
        $root->appendChild($legalMonetary);

        $contador = 1;

        foreach ($items as $item) {
            $lineNode = $doc->createElementNS(self::NS_CAC, 'cac:CreditNoteLine');
            $lineNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$contador));

            $cantidad = (float)($item['cantidad'] ?? 1);
            $qtyNode = $doc->createElementNS(self::NS_CBC, 'cbc:CreditedQuantity', number_format($cantidad, 2, '.', ''));
            $qtyNode->setAttribute('unitCode', $this->getUnidadMedida($item));
            $lineNode->appendChild($qtyNode);

            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $lineAmount = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($item['_subtotal'], 2, '.', ''));
            $lineAmount->setAttribute('currencyID', $moneda);
            $lineNode->appendChild($lineAmount);

            $pricingRef = $doc->createElementNS(self::NS_CAC, 'cac:PricingReference');
            $altCondition = $doc->createElementNS(self::NS_CAC, 'cac:AlternativeConditionPrice');
            $altPriceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($item['_precio_con_igv'], 2, '.', ''));
            $altPriceAmount->setAttribute('currencyID', $moneda);
            $altCondition->appendChild($altPriceAmount);
            $altCondition->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:PriceTypeCode', '01'));
            $pricingRef->appendChild($altCondition);
            $lineNode->appendChild($pricingRef);

            $taxTotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
            $taxAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem->setAttribute('currencyID', $moneda);
            $taxTotalItem->appendChild($taxAmountItem);

            $taxSubtotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
            $taxableAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($item['_subtotal'], 2, '.', ''));
            $taxableAmountItem->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxableAmountItem);

            $taxAmountItem2 = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem2->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxAmountItem2);

            $taxCategoryItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Percent', '18.00'));
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxExemptionReasonCode', $item['_afectacion']));

            $taxSchemeItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['_tax_id']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $item['_tax_name']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
            $taxCategoryItem->appendChild($taxSchemeItem);
            $taxSubtotalItem->appendChild($taxCategoryItem);
            $taxTotalItem->appendChild($taxSubtotalItem);
            $lineNode->appendChild($taxTotalItem);

            $itemNode = $doc->createElementNS(self::NS_CAC, 'cac:Item');
            $descripcion = htmlspecialchars($this->normalizeText($item['descripcion_item'] ?? $item['descripcion'] ?? 'Producto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $itemNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Description', $descripcion));

            if (!empty($item['codigo_interno'])) {
                $sellersId = $doc->createElementNS(self::NS_CAC, 'cac:SellersItemIdentification');
                $sellersId->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['codigo_interno']));
                $itemNode->appendChild($sellersId);
            }
            $lineNode->appendChild($itemNode);

            $priceNode = $doc->createElementNS(self::NS_CAC, 'cac:Price');
            $priceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($valorUnitario, 2, '.', ''));
            $priceAmount->setAttribute('currencyID', $moneda);
            $priceNode->appendChild($priceAmount);
            $lineNode->appendChild($priceNode);

            $root->appendChild($lineNode);
            $contador++;
        }

        $doc->appendChild($root);
        return $this->finalizeXml($doc);
    }

    // =========================================================================
    // NOTAS DE DÉBITO
    // =========================================================================
    public function generateNotaDebito(array $nota): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $doc->preserveWhiteSpace = false;

        $root = $doc->createElementNS(self::NS_DEBIT, 'DebitNote');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', self::NS_CAC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', self::NS_CBC);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DSIG);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);

        $extensions = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $extension  = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $extContent = $doc->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $extension->appendChild($extContent);
        $extensions->appendChild($extension);
        $root->appendChild($extensions);

        $root->appendChild($doc->createElementNS(self::NS_CBC, 'UBLVersionID', '2.1'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'CustomizationID', self::CUSTOMIZATION_NC_ND));

        // Inyección obligatoria para Notas de Crédito y Débito
        $profileId = $doc->createElementNS(self::NS_CBC, 'cbc:ProfileID', '0101');
        $profileId->setAttribute('schemeName', 'Tipo de Operacion');
        $profileId->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $profileId->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo51');
        $root->appendChild($profileId);

        $numeroCorrelativo = str_pad((string)($nota['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
        $idStr = ($nota['serie'] ?? 'FD01') . '-' . $numeroCorrelativo;
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $idStr));

        $fechaEmision = date('Y-m-d', strtotime($nota['fecha_emision'] ?? 'now'));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueDate', $fechaEmision));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IssueTime', date('H:i:s')));
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DebitNoteTypeCode', '08'));
        
        $moneda = $nota['moneda'] ?? 'PEN';
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DocumentCurrencyCode', $moneda));

        $origen = $nota['venta_origen'] ?? $nota['comprobante_origen'] ?? [];
        if (!empty($origen)) {
            $billingRef = $doc->createElementNS(self::NS_CAC, 'cac:BillingReference');
            $invDocRef  = $doc->createElementNS(self::NS_CAC, 'cac:InvoiceDocumentReference');
            $tipoOrigen = $origen['tipo_comprobante'] ?? '01';
            $serieOrigen = $origen['serie'] ?? 'F001';
            $numOrigen   = str_pad((string)($origen['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
            $invDocRef->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $serieOrigen . '-' . $numOrigen));
            $invDocRef->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DocumentTypeCode', $tipoOrigen));
            $billingRef->appendChild($invDocRef);
            $root->appendChild($billingRef);
        }

        $motivoCodigo = $nota['motivo_codigo'] ?? '01';
        $motivoDesc   = $nota['motivo_descripcion'] ?? 'Intereses por mora';
        $noteNode = $doc->createElementNS(self::NS_CBC, 'cbc:Note', htmlspecialchars('Motivo: ' . $motivoDesc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $noteNode->setAttribute('languageLocaleID', 'es-PE');
        $root->appendChild($noteNode);
        $root->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:DiscrepancyResponseCode', $motivoCodigo));

        $supplier = $doc->createElementNS(self::NS_CAC, 'cac:AccountingSupplierParty');
        $supplierParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        $supplierPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $emisorId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $this->getRucEmisor());
        $emisorId->setAttribute('schemeID', '6'); 
        $supplierPartyId->appendChild($emisorId);
        $supplierParty->appendChild($supplierPartyId);

        $partyName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $partyName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $supplierParty->appendChild($partyName);

        $partyTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $taxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $taxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $partyTaxScheme->appendChild($taxScheme);
        $supplierParty->appendChild($partyTaxScheme);

        $partyLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity'); 
        $partyLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($this->getRazonSocial()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        
        // FIX 3030 PARA NOTAS DE DÉBITO
        $address = $this->createAddress($doc, $this->getDireccionEmisor(), $this->getDistrito(), $this->getProvincia(), $this->getDepartamento(), $this->getUbigeo());
        $partyLegalEntity->appendChild($address);

        $supplierParty->appendChild($partyLegalEntity);

        $supplier->appendChild($supplierParty);
        $root->appendChild($supplier);

        $customer = $doc->createElementNS(self::NS_CAC, 'cac:AccountingCustomerParty');
        $customerParty = $doc->createElementNS(self::NS_CAC, 'cac:Party');
        $customerPartyId = $doc->createElementNS(self::NS_CAC, 'cac:PartyIdentification');
        $tipoDocCliente = $nota['cliente_tipo_doc'] ?? '6';
        $numDocCliente  = $nota['cliente_numero_doc'] ?? '00000000';
        $customerId = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $numDocCliente);
        $customerId->setAttribute('schemeID', (string)(int)$tipoDocCliente);
        $customerPartyId->appendChild($customerId);
        $customerParty->appendChild($customerPartyId);

        $customerName = $doc->createElementNS(self::NS_CAC, 'cac:PartyName');
        $customerName->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', htmlspecialchars($this->normalizeText($nota['cliente_nombre'] ?? $nota['razon_social'] ?? 'CLIENTE GENERICO'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerName);

        if (!empty($nota['cliente_direccion'])) {
            $customerParty->appendChild($this->createAddressSimplified($doc, $nota['cliente_direccion']));
        }
        $customerTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:PartyTaxScheme');
        $ctTaxScheme = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
        $ctTaxScheme->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', '6'));
        $customerTaxScheme->appendChild($ctTaxScheme);
        $customerParty->appendChild($customerTaxScheme);

        $customerLegalEntity = $doc->createElementNS(self::NS_CAC, 'cac:PartyLegalEntity'); 
        $customerLegalEntity->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:RegistrationName', htmlspecialchars($this->normalizeText($nota['cliente_nombre'] ?? $nota['razon_social'] ?? 'CLIENTE GENERICO'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $customerParty->appendChild($customerLegalEntity);

        $customer->appendChild($customerParty);
        $root->appendChild($customer);

        // =================================================================
        // PRE-CÁLCULO EXACTO TRIBUTOS GLOBALES
        // =================================================================
        $items = $nota['items'] ?? [];
        $totalesImpuestos = [
            '1000' => ['nombre' => 'IGV', 'tax_amount' => 0.0, 'taxable_amount' => 0.0],
            '9997' => ['nombre' => 'EXO', 'tax_amount' => 0.0, 'taxable_amount' => 0.0]
        ];

        $globalIgv = 0.0;
        $globalSubtotal = 0.0;
        $globalTotal = 0.0;

        foreach ($items as &$item) {
            $cantidad = (float)($item['cantidad'] ?? 1);
            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $precioUnitarioConIgv = (float)($item['precio_unitario_pen'] ?? ($valorUnitario * 1.18));
            
            $igvItem = ($precioUnitarioConIgv - $valorUnitario) * $cantidad;
            $subtotalItem = $valorUnitario * $cantidad;
            
            $isExonerated = $igvItem <= 0;
            $taxId = $isExonerated ? '9997' : '1000';
            
            $totalesImpuestos[$taxId]['tax_amount'] += $igvItem;
            $totalesImpuestos[$taxId]['taxable_amount'] += $subtotalItem;
            
            $globalIgv += $igvItem;
            $globalSubtotal += $subtotalItem;
            $globalTotal += ($subtotalItem + $igvItem);
            
            $item['_igv'] = $igvItem;
            $item['_subtotal'] = $subtotalItem;
            $item['_tax_id'] = $taxId;
            $item['_tax_name'] = $isExonerated ? 'EXO' : 'IGV';
            $item['_afectacion'] = $isExonerated ? '20' : '10';
            $item['_precio_con_igv'] = $precioUnitarioConIgv;
        }
        unset($item);

        $taxTotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
        $taxAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($globalIgv, 2, '.', ''));
        $taxAmount->setAttribute('currencyID', $moneda);
        $taxTotal->appendChild($taxAmount);

        foreach ($totalesImpuestos as $tId => $tData) {
            if ($tData['taxable_amount'] > 0 || $tData['tax_amount'] > 0) {
                $taxSubtotal = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
                
                $taxableAmount = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($tData['taxable_amount'], 2, '.', ''));
                $taxableAmount->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxableAmount);
                
                $taxAmountSub = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($tData['tax_amount'], 2, '.', ''));
                $taxAmountSub->setAttribute('currencyID', $moneda);
                $taxSubtotal->appendChild($taxAmountSub);
                
                $taxCategoryNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
                $taxSchemeNode = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
                
                // FIX STRICT TYPING: Cast explícito a (string) para la clave del array
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$tId));
                
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $tData['nombre']));
                $taxSchemeNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
                
                $taxCategoryNode->appendChild($taxSchemeNode);
                $taxSubtotal->appendChild($taxCategoryNode);
                $taxTotal->appendChild($taxSubtotal);
            }
        }
        $root->appendChild($taxTotal);

        $legalMonetary = $doc->createElementNS(self::NS_CAC, 'cac:RequestedMonetaryTotal');
        $totalSubtotal = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($globalSubtotal, 2, '.', ''));
        $totalSubtotal->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalSubtotal);
        $totalPagar = $doc->createElementNS(self::NS_CBC, 'cbc:PayableAmount', number_format($globalTotal, 2, '.', ''));
        $totalPagar->setAttribute('currencyID', $moneda);
        $legalMonetary->appendChild($totalPagar);
        $root->appendChild($legalMonetary);

        $contador = 1;

        foreach ($items as $item) {
            $lineNode = $doc->createElementNS(self::NS_CAC, 'cac:DebitNoteLine');
            $lineNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', (string)$contador));

            $cantidad = (float)($item['cantidad'] ?? 1);
            $qtyNode = $doc->createElementNS(self::NS_CBC, 'cbc:DebitedQuantity', number_format($cantidad, 2, '.', ''));
            $qtyNode->setAttribute('unitCode', $this->getUnidadMedida($item));
            $lineNode->appendChild($qtyNode);

            $valorUnitario = (float)($item['valor_unitario'] ?? 0);
            $lineAmount = $doc->createElementNS(self::NS_CBC, 'cbc:LineExtensionAmount', number_format($item['_subtotal'], 2, '.', ''));
            $lineAmount->setAttribute('currencyID', $moneda);
            $lineNode->appendChild($lineAmount);

            $pricingRef = $doc->createElementNS(self::NS_CAC, 'cac:PricingReference');
            $altCondition = $doc->createElementNS(self::NS_CAC, 'cac:AlternativeConditionPrice');
            $altPriceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($item['_precio_con_igv'], 2, '.', ''));
            $altPriceAmount->setAttribute('currencyID', $moneda);
            $altCondition->appendChild($altPriceAmount);
            $altCondition->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:PriceTypeCode', '01'));
            $pricingRef->appendChild($altCondition);
            $lineNode->appendChild($pricingRef);

            $taxTotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxTotal');
            $taxAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem->setAttribute('currencyID', $moneda);
            $taxTotalItem->appendChild($taxAmountItem);

            $taxSubtotalItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxSubtotal');
            $taxableAmountItem = $doc->createElementNS(self::NS_CBC, 'cbc:TaxableAmount', number_format($item['_subtotal'], 2, '.', ''));
            $taxableAmountItem->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxableAmountItem);

            $taxAmountItem2 = $doc->createElementNS(self::NS_CBC, 'cbc:TaxAmount', number_format($item['_igv'], 2, '.', ''));
            $taxAmountItem2->setAttribute('currencyID', $moneda);
            $taxSubtotalItem->appendChild($taxAmountItem2);

            $taxCategoryItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxCategory');
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Percent', '18.00'));
            $taxCategoryItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxExemptionReasonCode', $item['_afectacion']));

            $taxSchemeItem = $doc->createElementNS(self::NS_CAC, 'cac:TaxScheme');
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['_tax_id']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Name', $item['_tax_name']));
            $taxSchemeItem->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:TaxTypeCode', 'VAT'));
            $taxCategoryItem->appendChild($taxSchemeItem);
            $taxSubtotalItem->appendChild($taxCategoryItem);
            $taxTotalItem->appendChild($taxSubtotalItem);
            $lineNode->appendChild($taxTotalItem);

            $itemNode = $doc->createElementNS(self::NS_CAC, 'cac:Item');
            $descripcion = htmlspecialchars($this->normalizeText($item['descripcion_item'] ?? $item['descripcion'] ?? 'Producto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $itemNode->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:Description', $descripcion));

            if (!empty($item['codigo_interno'])) {
                $sellersId = $doc->createElementNS(self::NS_CAC, 'cac:SellersItemIdentification');
                $sellersId->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:ID', $item['codigo_interno']));
                $itemNode->appendChild($sellersId);
            }
            $lineNode->appendChild($itemNode);

            $priceNode = $doc->createElementNS(self::NS_CAC, 'cac:Price');
            $priceAmount = $doc->createElementNS(self::NS_CBC, 'cbc:PriceAmount', number_format($valorUnitario, 2, '.', ''));
            $priceAmount->setAttribute('currencyID', $moneda);
            $priceNode->appendChild($priceAmount);
            $lineNode->appendChild($priceNode);

            $root->appendChild($lineNode);
            $contador++;
        }

        $doc->appendChild($root);
        return $this->finalizeXml($doc);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================
    private function finalizeXml(DOMDocument $doc): string
    {
        $doc->formatOutput = true;
        // Obligamos a que el XML salga estrictamente como UTF-8
        $doc->encoding = 'UTF-8';
        $xml = $doc->saveXML();

        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);

        libxml_use_internal_errors(true);
        $v = new DOMDocument('1.0', 'UTF-8');
        if (!$v->loadXML($xml)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $parts = [];
            foreach ($errors as $err) {
                $parts[] = trim($err->message) . ' (line ' . $err->line . ')';
            }
            throw new \RuntimeException('XML inválido (no well-formed): ' . implode('; ', $parts));
        }

        return $xml;
    }

    public function saveRawXml(string $xmlContent, string $filename): string
    {
        $xmlContent = preg_replace('/^\xEF\xBB\xBF/', '', $xmlContent);
        $dir = STORAGE_PATH . '/xml';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $filename . '.xml';
        file_put_contents($path, $xmlContent);
        return $path;
    }

    private function createAddress(DOMDocument $doc, string $direccion, string $distrito, string $provincia, string $departamento, string $ubigeo): DOMElement
    {
        $address = $doc->createElementNS(self::NS_CAC, 'cac:RegistrationAddress');

        if (!empty($ubigeo)) {
            $ubigeoNode = $doc->createElementNS(self::NS_CBC, 'cbc:ID', $ubigeo);
            $ubigeoNode->setAttribute('schemeName', 'Ubigeos');
            $ubigeoNode->setAttribute('schemeAgencyName', 'PE:INEI');
            $address->appendChild($ubigeoNode);
        }

        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:AddressTypeCode', '0000'));
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:StreetName', htmlspecialchars($this->normalizeText($direccion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CitySubdivisionName', htmlspecialchars($this->normalizeText($distrito), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CityName', htmlspecialchars($this->normalizeText($provincia), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CountrySubentity', htmlspecialchars($this->normalizeText($departamento), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:CountrySubentityCode', 'PE'));

        $country = $doc->createElementNS(self::NS_CAC, 'cac:Country');
        $country->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IdentificationCode', 'PE'));
        $address->appendChild($country);

        return $address;
    }

    private function createAddressSimplified(DOMDocument $doc, string $direccion): DOMElement
    {
        $address = $doc->createElementNS(self::NS_CAC, 'cac:PostalAddress');
        $address->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:StreetName', htmlspecialchars($this->normalizeText($direccion), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));

        $country = $doc->createElementNS(self::NS_CAC, 'cac:Country');
        $country->appendChild($doc->createElementNS(self::NS_CBC, 'cbc:IdentificationCode', 'PE'));
        $address->appendChild($country);

        return $address;
    }

    private function getRucEmisor(): string
    {
        return $_ENV['SUNAT_RUC'] ?? $_ENV['EMPRESA_RUC'] ?? '20123456789';
    }

    private function getRazonSocial(): string
    {
        return $_ENV['EMPRESA_NOMBRE'] ?? 'EMPRESA DE INFORMÁTICA PUCALLPA';
    }

    private function getDireccionEmisor(): string
    {
        return $_ENV['EMPRESA_DIR'] ?? 'AV. CENTENARIO S/N - PUCALLPA';
    }

    private function getDistrito(): string
    {
        return $_ENV['EMPRESA_DISTRITO'] ?? 'CALLERIA';
    }

    private function getProvincia(): string
    {
        return $_ENV['EMPRESA_PROVINCIA'] ?? 'CORONEL PORTILLO';
    }

    private function getDepartamento(): string
    {
        return $_ENV['EMPRESA_DEPARTAMENTO'] ?? 'UCAYALI';
    }

    private function getUbigeo(): string
    {
        return $_ENV['EMPRESA_UBIGEO'] ?? '250001';
    }

    private function getTipoDocCliente(array $venta): int
    {
        $tipoDoc = $venta['cliente_tipo_doc'] ?? '0';
        return (int)$tipoDoc;
    }

    private function getNumDocCliente(array $venta): string
    {
        $numDoc = $venta['cliente_numero_doc'] ?? null;
        if (empty($numDoc)) {
            return '00000000';
        }
        return $numDoc;
    }

    private function getNombreCliente(array $venta): string
    {
        return $venta['cliente_nombre'] ?? $venta['razon_social'] ?? 'CLIENTE GENERICO';
    }

    /**
     * Fuerza estricta de validación UTF-8 para evitar caídas en DOMDocument
     */
    private function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
        return $text;
    }

    private function getUnidadMedida(array $item): string
    {
        $unidad = $item['unidad_medida'] ?? $item['unidad'] ?? '';
        return !empty($unidad) ? $unidad : 'NIU';
    }
}