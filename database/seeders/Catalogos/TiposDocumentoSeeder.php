<?php

declare(strict_types=1);

namespace Database\Seeders\Catalogos;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class TiposDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'PDF',  'nombre' => 'PDF',            'extension' => 'pdf',  'mime_type' => 'application/pdf',                                                        'tamano_max_mb' => 10, 'activo' => true, 'orden' => 10],
            ['codigo' => 'JPG',  'nombre' => 'Imagen JPEG',    'extension' => 'jpg',  'mime_type' => 'image/jpeg',                                                             'tamano_max_mb' => 5,  'activo' => true, 'orden' => 20],
            ['codigo' => 'PNG',  'nombre' => 'Imagen PNG',     'extension' => 'png',  'mime_type' => 'image/png',                                                              'tamano_max_mb' => 5,  'activo' => true, 'orden' => 30],
            ['codigo' => 'DOCX', 'nombre' => 'Word (docx)',    'extension' => 'docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'tamano_max_mb' => 10, 'activo' => true, 'orden' => 40],
            ['codigo' => 'XLSX', 'nombre' => 'Excel (xlsx)',   'extension' => 'xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',      'tamano_max_mb' => 10, 'activo' => true, 'orden' => 50],
            ['codigo' => 'CSV',  'nombre' => 'CSV',            'extension' => 'csv',  'mime_type' => 'text/csv',                                                               'tamano_max_mb' => 20, 'activo' => true, 'orden' => 60],
            ['codigo' => 'TXT',  'nombre' => 'Texto plano',    'extension' => 'txt',  'mime_type' => 'text/plain',                                                             'tamano_max_mb' => 5,  'activo' => true, 'orden' => 70],
        ];

        DB::table('tipos_documento')->upsert($rows, ['codigo'], ['nombre', 'extension', 'mime_type', 'tamano_max_mb', 'activo', 'orden']);
    }
}
