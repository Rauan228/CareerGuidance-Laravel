<?php

use App\Http\Controllers\Api\GlobalSpecialtyController;
use App\Http\Controllers\Api\QualificationController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\CollegeSpecializationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\EventsCalendarController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserApplicationsController; // Обновлено
use App\Http\Controllers\Api\CollegeQualificationController;
use App\Http\Controllers\CareerTestController;

Route::post('/admin/login', [AuthController::class, 'login']);
Route::post('/admin/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/institutions', [InstitutionController::class, 'index']);
Route::get('/institutions/{id}', [InstitutionController::class, 'show']);
Route::post('/institutions/register', [InstitutionController::class, 'register']);
Route::get('/global-specialties', [GlobalSpecialtyController::class, 'index']);

Route::get('/qualifications', [QualificationController::class, 'index']);   
Route::get('/global-specialties/{id}/qualifications', [GlobalSpecialtyController::class, 'getQualificationsWithSpecializations']);

Route::get('/specilizations', [SpecializationController::class, 'index']);  
Route::get('/specializations', [SpecializationController::class, 'index']);

Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/user-login', [UserAuthController::class, 'login']);
Route::post('/user-logout', [UserAuthController::class, 'logout'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/current-user', [UserController::class, 'getCurrentUser']);
Route::middleware('auth:sanctum')->get('/liked-institutions', [InstitutionController::class, 'getLikedInstitutions']);
Route::delete('/institutions/{id}/unlike', [InstitutionController::class, 'unlike'])->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->get('/current-user', function (Request $request) {
    return response()->json($request->user());
});
Route::middleware('auth:sanctum')->post('/institutions/{id}/like', [InstitutionController::class, 'like']);

Route::middleware('auth:sanctum')->get('/notifications', [NotificationController::class, 'getUserNotifications']);
Route::middleware('auth:sanctum')->post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

// Получить список всех событий
Route::get('/events', [EventsCalendarController::class, 'index']);

// Получить события по ID университета
Route::get('/institutions/{institutionId}/events', [EventsCalendarController::class, 'getEventsByInstitution']);
Route::get('/institutions/{id}/reviews', [InstitutionController::class, 'getReviews']);
Route::middleware('auth:sanctum')->post('/institutions/{id}/reviews', [InstitutionController::class, 'storeReview']);
Route::post('/institutions/login', [InstitutionController::class, 'login']);
Route::middleware('auth:sanctum')->get('/institutions/current', [InstitutionController::class, 'getCurrentInstitution']);

// Получить детали события по ID
Route::get('/events/{id}', [EventsCalendarController::class, 'show']);
Route::post('/events/{id}/apply', [EventsCalendarController::class, 'apply']);

// Создать новое событие
Route::post('/events', [EventsCalendarController::class, 'store']);

// Обновить событие по ID
Route::put('/events/{id}', [EventsCalendarController::class, 'update']);

// Удалить событие по ID
Route::delete('/events/{id}', [EventsCalendarController::class, 'destroy']);

Route::delete('/institutions/{id}/unlike', [InstitutionController::class, 'unlike'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/user-applications', [UserApplicationsController::class, 'store']);
    Route::get('/user-applications', [UserApplicationsController::class, 'getUserApplications']);
    Route::get('/user-reviews', [UserController::class, 'getUserReviews']); // Новый маршрут для отзывов
    Route::get('/career-tests', [CareerTestController::class, 'index']);
    Route::post('/career-tests', [CareerTestController::class, 'store']);
    Route::get('/career-tests/{careerTestResult}', [CareerTestController::class, 'show']);
});

// Маршруты для специальностей (университетских и колледжных)
Route::prefix('specialties')->group(function () {
    // Получить все специальности (с опциональным параметром type для фильтрации)
    Route::get('/', [GlobalSpecialtyController::class, 'index']);
    
    // Получить конкретную специальность
    Route::get('/{id}', [GlobalSpecialtyController::class, 'show']);
    
    // Создать новую специальность
    Route::post('/', [GlobalSpecialtyController::class, 'store']);
    
    // Обновить специальность
    Route::put('/{id}', [GlobalSpecialtyController::class, 'update']);
    
    // Удалить специальность
    Route::delete('/{id}', [GlobalSpecialtyController::class, 'destroy']);
    
    // Получить квалификации и специализации для университетской специальности
    Route::get('/{id}/qualifications', [GlobalSpecialtyController::class, 'getQualificationsWithSpecializations']);
});

// Маршруты для колледжных специальностей
Route::prefix('college-specializations')->group(function () {
    Route::get('/', [CollegeSpecializationController::class, 'index']);
    Route::get('/{id}', [CollegeSpecializationController::class, 'show']);
    Route::post('/', [CollegeSpecializationController::class, 'store']);
    Route::put('/{id}', [CollegeSpecializationController::class, 'update']);
    Route::delete('/{id}', [CollegeSpecializationController::class, 'destroy']);
    Route::get('/{id}/institutions', [CollegeSpecializationController::class, 'institutions']);
});

Route::get('specializations/{id}/institutions', [SpecializationController::class, 'institutions']);
// ВАЖНО: /specialties/{id} уже обрабатывается GlobalSpecialtyController::show (направление).
// Дубль с SpecializationController здесь перебивал его и возвращал конкретную специализацию —
// из-за этого в шапке квалификаций показывалось не то название. Специализация по id — через /specializations/{id}.
Route::get('specializations/{id}', [SpecializationController::class, 'show']);

Route::middleware('api')->group(function () {
    // Глобальные специальности (университеты и колледжи)
    Route::get('global-specialties', [GlobalSpecialtyController::class, 'index']);
    Route::get('global-specialties/{id}', [GlobalSpecialtyController::class, 'show']);
    Route::get('global-specialties/{id}/qualifications', [GlobalSpecialtyController::class, 'getQualificationsWithSpecializations']);
    
    // Специальности колледжей
    Route::get('college-specializations', [CollegeSpecializationController::class, 'index']);
    Route::get('college-specializations/{id}', [CollegeSpecializationController::class, 'show']);
    Route::get('college-specializations/{id}/institutions', [CollegeSpecializationController::class, 'institutions']);

    // Квалификации колледжей
    Route::get('college-qualifications', [CollegeQualificationController::class, 'index']);
    Route::get('college-qualifications/{id}', [CollegeQualificationController::class, 'show']);
});

Route::get('/college-qualifications', function (Request $request) {
    $ids = explode(',', $request->query('ids', ''));
    return \App\Models\CollegeQualification::whereIn('id', $ids)
        ->select('id', 'qualification_name', 'description')
        ->get();
});

// Routes for authenticated institution panel
Route::middleware(['auth:sanctum'])->prefix('institution')->group(function () {
    // profile
    Route::get('/profile', [\App\Http\Controllers\Api\InstitutionProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\Api\InstitutionProfileController::class, 'update']);

    // events
    Route::get('/events', [\App\Http\Controllers\Api\InstitutionEventController::class, 'index']);
    Route::post('/events', [\App\Http\Controllers\Api\InstitutionEventController::class, 'store']);
    Route::put('/events/{id}', [\App\Http\Controllers\Api\InstitutionEventController::class, 'update']);
    Route::delete('/events/{id}', [\App\Http\Controllers\Api\InstitutionEventController::class, 'destroy']);

    // specialties
    Route::get('/specialties', [\App\Http\Controllers\Api\InstitutionSpecialtyController::class, 'index']);
    Route::get('/specialties/available', [\App\Http\Controllers\Api\InstitutionSpecialtyController::class, 'available']);
    Route::post('/specialties', [\App\Http\Controllers\Api\InstitutionSpecialtyController::class, 'store']);
    Route::put('/specialties/{id}', [\App\Http\Controllers\Api\InstitutionSpecialtyController::class, 'update']);
    Route::delete('/specialties/{id}', [\App\Http\Controllers\Api\InstitutionSpecialtyController::class, 'destroy']);

    // user applications
    Route::get('/applications', [\App\Http\Controllers\Api\InstitutionApplicationController::class, 'index']);
    Route::put('/applications/{id}', [\App\Http\Controllers\Api\InstitutionApplicationController::class, 'update']);

    // group applications
    Route::get('/group-applications', [\App\Http\Controllers\Api\InstitutionGroupApplicationController::class, 'index']);
    Route::put('/group-applications/{id}', [\App\Http\Controllers\Api\InstitutionGroupApplicationController::class, 'update']);
});

Route::get('/university/catalog', \App\Http\Controllers\Api\UniversityCatalogController::class);