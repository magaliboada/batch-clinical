<?php namespace App\Command;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Console\Command;

class QueryBuilder extends Command
{
    protected $connection = null;
    protected $batchBuilder = null;

    public function __construct()
    {
        $this->batchBuilder = new BatchBuilder();
        $tableNames = ['CreditNotes', 'Invoices', 'Receipts', 'Settlements'];

        foreach ($tableNames as $tableName) {
            $this->loadModel($tableName);
        }
    }


    public function queryCreditNotes($id)
    {
        $creditnotes = $this
            ->CreditNotes
            ->find()
            ->select(['id','patient_id','tax_entity_id','invoice_id','Invoices.invoice_number','date_transaction'])
            ->contain(['Invoices'])
            ->where(['CreditNotes.navision_transfer_id' => $id])
            ->where(['CreditNotes.navision_transfer_status' => 1]);

        $header = ['ID_ABONAMENT', 'ID_PATIENT', 'ID_ENTITAT_FISCAL', 'ID_FACTURA', 'NUMERO_FACTURA', 'DATA_ABONAMENT'];
        $returnValues['header'] = $header ;
        $returnValues['array'] = json_decode(json_encode($creditnotes), true);

        foreach ($returnValues['array'] as &$returnValue) {
            $returnValue['date_transaction'] = $this->batchBuilder->customFormatDate($returnValue['date_transaction']);
            $returnValue = $this->batchBuilder->moveKeyBefore($returnValue, 'date_transaction', 'invoice');
        }

        return $returnValues;
    }

    public function queryReceipts($id)
    {
        $receipts = $this
            ->Receipts
            ->find()
            ->select(['id','patient_id','tax_entity_id','total_payment','method_payment','Banks.navision_code', 'active', 'date_payment'])
            ->contain(['Banks'])
            ->where(['Receipts.navision_transfer_id' => $id])
            ->where(['Receipts.navision_transfer_status' => 1]);

        $returnValues['header'] = ['ID_REBUT', 'ID_PACIENT', 'ID_ENTITAT_FISCAL', 'IMPORT_REALITZAT', 'METODE_PAGAMENT', 'ENTITAT_PAGAMENT', 'TIPUS_PAGAMENT', 'DATA_PAGAMENT'];
        $returnValues['array'] = json_decode(json_encode($receipts), true);

        foreach ($returnValues['array'] as &$returnValue) {
            $returnValue = $this->batchBuilder->moveKeyBefore($returnValue, 'active', 'bank');
            $returnValue['date_payment'] = $this->batchBuilder->customFormatDate($returnValue['date_payment']);

            if ($returnValue['total_payment'] < 0) {
                $returnValue['active'] = 'D';
            } else {
                $returnValue['active'] = 'P';
            }

            $returnValue['total_payment'] = abs($returnValue['total_payment']);
            $returnValue['total_payment'] = number_format($returnValue['total_payment'], 2, ',', '.');
        }

        return $returnValues;
    }


    public function querySettlements($id)
    {
        $settlements = $this
            ->Settlements
            ->find()
            ->select(
                [
                'id',
                'receipt_id',
                'dest_receipt_id',
                'dest_invoice_id',
                'type_settlement',
                'total_settlement',
                'Invoices.invoice_number',
                'date_transaction'
                ]
            )
            ->contain(['Invoices'])
            ->where(['Settlements.navision_transfer_id' => $id])
            ->where(['Settlements.navision_transfer_status' => 1])
            ->order(['date_transaction' => 'ASC']);

        $returnValues['header'] = [
            'ID_LIQUIDACIO',
            'ID_REBUT_ORIGEN',
            'ID_REBUT_DESTI',
            'ID_FACTURA_DESTI',
            'TIPUS_LIQUIDACIO',
            'IMPORT_LIQUIDACIO',
            'NUMERO_FACTURA',
            'DATA_LIQUIDACIO'
        ];
        $returnValues['array'] = json_decode(json_encode($settlements), true);

        foreach ($returnValues['array'] as &$returnValue) {
            $returnValue['total_settlement'] = number_format($returnValue['total_settlement'], 2, ',', '.');
            $returnValue['date_transaction'] = $this->batchBuilder->customFormatDate($returnValue['date_transaction']);
            $returnValue = $this->batchBuilder->moveKeyBefore($returnValue, 'date_transaction', 'invoice');
        }

        return $returnValues;
    }

    public function queryInvoices($id, $version)
    {

        $invoices = $this
            ->Invoices
            ->find('all')
            ->select(
                ['id','tax_entity_id','surgery_budget_id','patient_id','invoice_date',
                'invoice_number','tax_entity_type','company_name','tax_number','address','city','postal_code',
                'region','country','email','phone1','phone2', 'CountryCodes.nav_reg_group']
            )
            ->where(['Invoices.navision_transfer_id' => $id])
            ->where(['Invoices.navision_transfer_status' => 1])
            ->contain(['InvoiceLines','CountryCodes'])
            ->order(['invoice_number' => 'ASC']);

        $invoices = json_decode(json_encode($invoices), true);

        $iheader = 'TIPUS REGISTRE;TOTAL_LINIES;ID_FACTURA;ID_ENTITAT_FISCAL;ID_PRESSUPOST_INTERVENCIO;ID_PACIENT;DATA_FACTURA;NUMERO_FACTURA;TIPUS_ENTITAT_FISCAL;NOM_EMPRESA;NIF/CIF/PASAPORT;ADREÇA_FISCAL;CIUTAT_FISCAL;CODI_POSTAL_FISCAL;REGIO_FISCAL;PAIS_FISCAL;CORREU_ELECTRONIC;TELEFON_1;TELEFON_2'. PHP_EOL;
        $ilheader = 'TIPUS REGISTRE;NUMERO_LINIA;ID_LINIA;ID_FACTURA;ID_DOCTOR;ID_BROKER;DESCRIPCIO_PRODUCTE_ES;PREU;IVA;NUMERO_UNITATS;DATA_ACTE_MEDIC'. PHP_EOL;

        /**
* DATE FORMAT 
*/
        $csv = "";
        //$csv .= $iheader;

        foreach ($invoices as $invoice) {
            //Tipus Registre
            $csv .= 'C;';

            //Total Linies
            $csv .= count($invoice['invoice_lines']).';';

            $csv .= $this->batchBuilder->cleanField($invoice['id']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['tax_entity_id']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['surgery_budget_id']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['patient_id']).';';
            $csv .= $this->batchBuilder->customFormatDate($invoice['invoice_date']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['invoice_number']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['tax_entity_type']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['company_name']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['tax_number']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['address']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['city']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['postal_code']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['region']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['country']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['email']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['phone1']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['phone2']).';';
            $csv .= $this->batchBuilder->cleanField($invoice['country_code']['nav_reg_group']).';';

            $csv .= PHP_EOL;

            foreach ($invoice['invoice_lines'] as $index => $invoiceLine) {
                $csv .= 'L'.';';
                $csv .= $index+1 .';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['id']).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['invoice_id']).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['doctor_id']).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['broker_id']).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['concept_es']).';';
                $csv .= $this->batchBuilder->cleanField(number_format($invoiceLine['price'], 2, ',', '.')).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['iva']).';';
                $csv .= $this->batchBuilder->cleanField($invoiceLine['units']).';';
                $csv .= $this->batchBuilder->customFormatDate($invoiceLine['medical_act_date']).';';

                $csv .= PHP_EOL;
            }

        }

        if (strlen($csv) > 0) {
            Configure::load('config_vars');
            $realPath = Configure::read('paths.export');
            $folderName = Configure::read('paths.folder');

            if (!file_exists($realPath.'/'.$folderName)) {
                mkdir($realPath.'/'.$folderName, 0755, true);
            }

            $currentPath = realpath($realPath.'/'.$folderName);
            $csv = mb_convert_encoding($csv, 'ISO-8859-1', 'UTF-8');

            $csv_filename =  'INVOICES'.date("Y-m-d")."_".$version.".csv";
            $csv_handler = fopen($currentPath.'/'.$csv_filename, 'w');

            fwrite($csv_handler, $csv);
            fclose($csv_handler);
        }
    }


}



