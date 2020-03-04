<?php namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;

class BatchProcessCommand extends Command
{


    protected $batchErrors = [
            0 => 'Error: Could not start a new Batch Process.',
            1 => 'Error: Could not mark elements with a status and batch process ID. No elements pending.'
        ];

    protected $fileVersion = 0;

    public function initialize()
    {
        parent::initialize();
    }

    public function execute(Arguments $args, ConsoleIo $io)
    {
        //starting process
        $id = $this->startBatch();
        $this->updateNavisionTransfer($id);
        $queryBuilder = new QueryBuilder();
        $batchBuilder = new BatchBuilder();

        $queryResults = $queryBuilder->queryCreditNotes($id);
        $batchBuilder->arrayToCSV($queryResults['header'], $queryResults['array'], 'CREDITNOTES', $this->fileVersion);

        $queryResults = $queryBuilder->queryReceipts($id);
        $batchBuilder->arrayToCSV($queryResults['header'], $queryResults['array'], 'RECEIPTS', $this->fileVersion);

        $queryResults = $queryBuilder->querySettlements($id);
        $batchBuilder->arrayToCSV($queryResults['header'], $queryResults['array'], 'SETTLEMENTS', $this->fileVersion);

        $queryBuilder->queryInvoices($id, $this->fileVersion);

        //files in root /export folder
        $this->endBatch($id);
    }


    //Starting batch process creating an ID for the process.
    protected function startBatch()
    {
        $io = new ConsoleIo();
        //version del excel
        $navisionTable = TableRegistry::getTableLocator()->get('NavisionTransfers');

        $versionResult = $navisionTable->find()
            ->select(['version' => 'COUNT(NavisionTransfers.date_process)+1'])
            ->where('NavisionTransfers.date_process=DATE(NOW())')
            ->first()
            ->toArray();

        $this->fileVersion = $versionResult['version'];

        $navision = $navisionTable->newEntity();

        $navision->date_process = date("Y-m-d", time());
        $navision->process_started = date("Y-m-d h:m:s", time());

        if ($navisionTable->save($navision)) {
            $id = $navision->id;
        } else {
            $io->out($this->batchErrors[0]);
            exit();
        }

        return $id;
    }

    //Looping through all element to be exported marking them with a status and batch process ID.
    protected function updateNavisionTransfer($id)
    {
        $io = new ConsoleIo();
        $tableNames = ['CreditNotes', 'Invoices', 'Receipts', 'Settlements'];
        $checkRegistries = 0;

        foreach ($tableNames as $tableName) {
            $this->loadModel($tableName);

            $checkRegistries += $this->$tableName->updateAll(
                ['navision_transfer_id' => $id, 'navision_transfer_status ' => 1],
                ['navision_transfer_id IS NULL']
            );
        }

        if ($checkRegistries <= 0) {
            $this->loadModel('NavisionTransfers');
            $navisionRegistry = $this->NavisionTransfers->get($id);
            $this->NavisionTransfers->delete($navisionRegistry);
            $io->out($this->batchErrors[1]);
            exit();
        }
    }

    //Finishing the process, setting the ending date and time.
    protected function endBatch($id)
    {
        $navisionTable = TableRegistry::getTableLocator()->get('NavisionTransfers');
        $navision = $navisionTable->get($id);

        $navision->process_completed = date("Y-m-d h:m:s", time());
        $navisionTable->save($navision);
    }
}
