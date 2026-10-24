<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_pages', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('number');
        });

        $jobs = DB::table('print_jobs')->pluck('id');

        foreach ($jobs as $jobId) {
            $pages = DB::table('print_pages')
                ->join('print_documents', 'print_documents.id', '=', 'print_pages.print_document_id')
                ->where('print_documents.print_job_id', $jobId)
                ->orderBy('print_documents.position')
                ->orderBy('print_pages.number')
                ->orderBy('print_pages.id')
                ->select('print_pages.id')
                ->get();

            $position = 0;
            foreach ($pages as $page) {
                $position++;
                DB::table('print_pages')->where('id', $page->id)->update(['position' => $position]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('print_pages', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
