<?php

namespace App\Console\Commands;

use App\Models\Icd;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IcdImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'icd:import {file=icd10_codes.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import ICD-10 codes from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = base_path($this->argument('file'));

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing ICD codes from {$filePath}...");

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle); // Skip header

        if (!$header || count($header) < 2) {
            $this->error("Invalid CSV format. Expected [Code, Description]");
            fclose($handle);
            return 1;
        }

        $count = 0;
        $batch = [];
        $batchSize = 500;
        $now = now();

        DB::beginTransaction();
        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) < 2) continue;

                $batch[] = [
                    'code' => trim($data[0]),
                    'description' => trim($data[1]),
                    'abbreviation' => isset($data[2]) ? trim($data[2]) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $count++;

                if (count($batch) >= $batchSize) {
                    Icd::upsert($batch, ['code'], ['description', 'abbreviation', 'updated_at']);
                    $batch = [];
                    $this->output->write('.');
                }
            }

            if (!empty($batch)) {
                Icd::upsert($batch, ['code'], ['description', 'abbreviation', 'updated_at']);
            }

            DB::commit();
            $this->newLine();
            $this->info("Successfully imported {$count} ICD codes.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Import failed: " . $e->getMessage());
            Log::error("ICD Import failed: " . $e->getMessage());
            return 1;
        } finally {
            fclose($handle);
        }

        return 0;
    }
}
