<?php

namespace App\Repositories;

use App\Models\Faculty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FacultyRepository
{
    public function getfacultyList(){
        return Faculty::select('faculties.*')
            ->selectRaw('COUNT(articles.article_id) AS articleCount')
            ->join('system_datas', 'system_datas.faculty_id', '=', 'faculties.faculty_id')
            ->join('articles', 'system_datas.system_id', '=', 'articles.system_id')
            ->groupBy('faculties.faculty_id')
            ->get();
    }
    
    public function getAllFaculties()
    {
        return $faculties = DB::table('faculties as f')
        ->select([
            'f.faculty_id',
            'f.faculty_name',
            'f.remark',
            'f.updated_at'
        ])
        ->orderBy('created_at', 'asc')
        ->get();
    }     

    public function selectFacultyByID($faculty_id)
    {
        $faculty = DB::table('faculties')
            ->where('faculty_id', $faculty_id) 
            ->first();

        if (!$faculty) {
            return false;
        }

        return $faculty;
    }

    public function createFaculty($data)
    {
        $facultyId = Str::uuid()->toString();

        DB::table('faculties')->insert([
            'faculty_id'   => $facultyId,
            'faculty_name' => $data['faculty_name'], 
            'remark'       => $data['remark']??null,
            'created_at'   => now(),
            'updated_at'   => now()
        ]);

        return ['faculty_id' => $facultyId];
    }
    
    public function update(array $data)
    {
        return DB::transaction(function () use ($data) {
            $existing = DB::table('faculties')
                ->where('faculty_id', $data['faculty_id'])
                ->first();

            if (!$existing || $existing->updated_at != $data['updated_at']) {
                return false;
            }

            DB::table('faculties')
                ->where('faculty_id', $data['faculty_id'])
                ->update([
                    'faculty_name' => $data['faculty_name'] ?? $existing->faculty_name,
                    'remark'       => $data['remark'] ?? $existing->remark,
                    'updated_at'   => now(),
                ]);

            return $this->selectFacultyByID($data['faculty_id']);
        });
    }  
}