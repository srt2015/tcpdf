<?php
class Autoloader {
    static public function loader($className) {
        $filename = str_replace('\\', '/', $className) . ".php";
        if (file_exists($filename)) {
            require_once($filename);
            if (class_exists($className)) {
                return TRUE;
            }
        }
        return FALSE;
    }
}
spl_autoload_register('Autoloader::loader');

require_once($_SERVER["DOCUMENT_ROOT"]."/[system path]/tcpdf/common/tcpdf_autoconfig.php");
include_once($_SERVER["DOCUMENT_ROOT"]."/[system path]/tcpdf/common/tcpdf_config.php");

$pdf=new \tcpdf\Tcpdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetFont('dejavusans', '', 14, '', true);
$pdf->AddPage();
$pdf->Write(0, "űőóöéá", '', 0, 'C', true, 0, false, false, 0);
$pdf->Output('example_001.pdf', 'I');