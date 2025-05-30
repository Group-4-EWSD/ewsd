<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
//use Your Model

/**
 * Class UserRepository.
 */
class UserRepository
{
    /**
     * @return string
     *  Return the model
     */
    public function model()
    {
        return User::class;
    }

    public function findByEmail($email)
    {
        return $this->model()::select([
            'users.id',
            'users.user_name',
            'users.nickname',
            'users.user_email',
            'users.user_password',
            'users.user_type_id',
            'ut.user_type_name',
            'f.faculty_name',
            'users.gender',
            'users.date_of_birth',
            'users.phone_number',
            DB::raw("CONCAT('https://ewsdcloud.s3.ap-southeast-1.amazonaws.com/', users.user_photo_path) AS user_photo_path"),
        ])
            ->where('users.user_email', $email)
            ->leftJoin('user_types as ut', 'ut.user_type_id', '=', 'users.user_type_id')
            ->leftJoin('faculties as f', 'f.faculty_id', '=', 'users.faculty_id')
            ->first();
    }

    public function getUserById($id)
    {
        return DB::table('users as u')
            ->select('u.id','u.user_name','u.user_email','u.nickname','u.user_type_id','u.faculty_id','u.gender','u.date_of_birth','u.phone_number','u.user_photo_path','ut.user_type_name','f.faculty_name')
            ->join('user_types as ut', 'ut.user_type_id', '=', 'u.user_type_id')
            ->join('faculties as f', 'f.faculty_id', '=', 'u.faculty_id')
            ->where('id', $id)
            ->first();
    }

    public function updateUser($id, $data)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $this->getUserById($id);
    }

    public function editUser($data)
    {
        $user = User::findorFail($data['id']);
        $user->update($data);
        $return = $this->getUserById($data['id']);
        return $return;
    }

    public function findById(string $id): ?User
    {
        return User::find($id);
    }

    public function updatePhoto(string $id, ?string $photoPath): bool
    {
        $user = $this->findById($id);
        if (!$user) {
            return false;
        }

        $user->user_photo_path = $photoPath;
        return $user->save();
    }

    public function getGuestList()
    {
        return DB::table('users as u')
            ->select([
                'u.user_name',
                'u.user_email',
                'f.faculty_name',
                'u.date_of_birth',
                'u.gender',
                'u.phone_number'
            ])
            ->join('faculties as f', 'f.faculty_id', 'u.faculty_id')
            ->where('u.user_type_id', '=', '0')
            ->get();
    }
    
    public function getMemberList()
    {
        return DB::table('users as u')
            ->select([
                'u.user_name',
                'u.nickname',
                DB::raw("CONCAT('https://ewsdcloud.s3.ap-southeast-1.amazonaws.com/', u.user_photo_path) AS user_photo_path"),
                'u.user_email',
                'f.faculty_name',
                'ut.user_type_name',
                'u.date_of_birth',
                'u.gender',
                'u.phone_number'
            ])
            ->join('faculties as f', 'f.faculty_id', '=', 'u.faculty_id')
            ->join('user_types as ut', 'ut.user_type_id', '=', 'u.user_type_id')
            ->where('u.user_type_id', '=', 2)
            ->get();
    }

    public function getActiveUserList($request)
    {
        return DB::table('users as u')
            ->leftJoin('articles as a', 'a.user_id', '=', 'u.id')
            ->leftJoin('actions as act', function ($join) {
                $join->on('act.user_id', '=', 'u.id')
                    ->where('act.react', '=', '1');
            })
            ->leftJoin('comments as c', 'c.user_id', '=', 'u.id')
            ->leftJoin('system_datas as sd', 'sd.system_id', '=', 'a.system_id')
            ->leftJoin('academic_years as ay', 'ay.academic_year_id', '=', 'sd.academic_year_id')
            ->join('faculties as f', 'f.faculty_id', '=', 'u.faculty_id')
            ->join('user_types as ut', 'ut.user_type_id', '=', 'u.user_type_id')
            ->selectRaw('
                u.id,
                u.user_name,
                u.nickname,
                u.user_email,
                u.gender,
                u.user_type_id,
                ut.user_type_name,
                u.faculty_id,
                f.faculty_name,
                u.phone_number,
                CONCAT("https://ewsdcloud.s3.ap-southeast-1.amazonaws.com/", u.user_photo_path) AS user_photo_path,
                u.date_of_birth,
                COUNT(DISTINCT a.article_id) as article_count, 
                COUNT(DISTINCT act.action_id) as action_count, 
                COUNT(DISTINCT c.comment_id) as comment_count, 
                ((COUNT(DISTINCT a.article_id) * 20) + 
                (COUNT(DISTINCT act.action_id) * 1) + 
                (COUNT(DISTINCT c.comment_id) * 1)) as total_score
            ')
            // ->where('u.user_type_id', '1') // Only Student List
            ->when(
                $request && isset($request->userId) && $request->userId,
                fn($query) => $query->where('u.id', $request->userId)
            )
            ->when(
                $request && isset($request->userName) && $request->userName,
                fn($query) => $query->where('u.user_name', 'LIKE', "%{$request->userName}%")
            )
            ->when(
                $request && isset($request->facultyId) && $request->facultyId,
                fn($query) => $query->where('u.faculty_id', $request->facultyId)
            )
            ->when(
                $request && isset($request->academicYear) && $request->academicYear,
                fn($query) => $query->where('ay.academic_year_start', $request->academicYear)
            )
            ->groupBy('u.id')
            ->get();
    }

    public function getMostUsedBrowserList(){
        $browserFreq = DB::table('login_histories as lh')
            ->join('browsers as b', 'b.browser_id', '=', 'lh.browser_id')
            ->select([
                'b.browser_id',
                'b.browser_name',
                DB::raw('COUNT(lh.id) as login_count')
            ])
            ->groupBy('lh.browser_id')
            ->get();
        return $browserFreq;
    }

    public function isUserVisitExist($userId, $pageId){
        return DB::table('view_pages')
            ->where('user_id', $userId)
            ->where('page_id', $pageId)
            ->exists();
    }

    public function addUserVisit($userId, $pageId){
        DB::table('view_pages')->insert([
            'user_id' => $userId,
            'page_id' => $pageId,
            'visit_datetime' => now()
        ]);
    }

    public function getMostViewedPageVisit(){
        $mostViewedPages = DB::table('view_pages as vp')
            ->join('app_pages as p', 'vp.page_id', '=', 'p.app_page_id')
            ->select('vp.page_id', 'p.app_page_name', DB::raw('COUNT(vp.page_id) as view_count'))
            ->groupBy('vp.page_id')
            ->orderBy('view_count', 'desc')
            ->get();
        return $mostViewedPages;
    }

    public function getAllUserList()
    {
        return DB::table('users as u')
            ->select([
                'u.id',
                'u.user_name',
                'u.nickname',
                'u.user_email',
                'u.user_type_id',
                'ut.user_type_name',
                'u.faculty_id',
                'f.faculty_name',
                'u.gender',
                'u.date_of_birth',
                'u.phone_number',
                'u.user_photo_path',
                'u.created_at',
                'lh.last_login_datetime',
            ])
            ->join('user_types as ut', 'ut.user_type_id', '=', 'u.user_type_id') 
            ->leftJoin('faculties as f', 'f.faculty_id', '=', 'u.faculty_id') 
            ->leftJoin(DB::raw('(SELECT user_id, MAX(login_datetime) as last_login_datetime FROM login_histories GROUP BY user_id) as lh'), 'lh.user_id', '=', 'u.id')
            ->orderBy('u.created_at', 'asc')
            ->get();
    }

    public function getUserListByType($userType)
    {
        return DB::table('users as u')
        ->select([
                'u.id',
                'u.user_name',
                'u.nickname',
                'u.user_email',
                'u.user_type_id',
                'ut.user_type_name',
                'u.faculty_id',
                'f.faculty_name',
                'u.gender',
                'u.date_of_birth',
                'u.phone_number',
                'u.user_photo_path',
                'u.created_at',
                'lh.last_login_datetime',
            ])
            ->join('user_types as ut', 'ut.user_type_id', '=', 'u.user_type_id')
            ->leftJoin('faculties as f', 'f.faculty_id', '=', 'u.faculty_id')
            ->leftJoin(DB::raw('(SELECT user_id, MAX(login_datetime) as last_login_datetime FROM login_histories GROUP BY user_id) as lh'), 'lh.user_id', '=', 'u.id')
            ->where('u.user_type_id', '=', $userType)
            ->orderBy('u.created_at', 'asc')
            ->get();
    }

    public function updatePassword($userId, $hashedPassword)
    {
        return DB::table('users')
            ->where('id', $userId)
            ->update(['user_password' => $hashedPassword]);
    }

    function generateUserId()
    {
        $latestUser = DB::table('users')
                        ->select('id')
                        ->orderBy('id', 'desc')
                        ->first();
    
        if ($latestUser) {
            $latestIdNumber = (int) substr($latestUser->id, 1); 
    
            $newIdNumber = $latestIdNumber + 1;
        } else {
            $newIdNumber = 1;
        }

        $newUserId = 'U' . str_pad($newIdNumber, 4, '0', STR_PAD_LEFT);
        return $newUserId;
    }    
    
    public function userRegister($data)
    {
        DB::table('users')->insert([
            'id' => $data['user_id'],
            'user_name' => $data['user_name'],
            'nickname' => $data['nickname'] ?? null,
            'user_email' => $data['user_email'],
            'user_password' => Hash::make($data['user_password']),
            'user_type_id' => $data['user_type_id'],
            'faculty_id' => $data['faculty_id'],
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $data;
    }

    public function userLastLogin($userId)
    {
        $lastLogin = DB::table('login_histories')
            ->where('user_id', $userId)
            ->select('login_datetime')
            ->orderBy('login_datetime', 'desc')
            ->first();
    
        return $lastLogin->login_datetime;
    }       

    public function userRepository($userId)
    {
        return DB::table('users')
            ->where('user_id', $userId)
            ->first();
    }
}