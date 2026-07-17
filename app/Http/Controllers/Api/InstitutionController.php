<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Institution;
use Illuminate\Support\Facades\Auth;
use App\Models\Like;
use Illuminate\Support\Facades\DB;
use App\Models\InstitutionApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InstitutionController extends Controller
{
    public const INDEX_CACHE_KEY = 'api.institutions.index';

    public function index()
    {
        try {
            // Полные reviews/likes списку не нужны — фронт использует только
            // reviews_count / likes_count / reviews_avg_rating.
            // Кэшируем готовый JSON: БД удалённая, каждый запрос к ней дорогой.
            $institutions = \Cache::remember(self::INDEX_CACHE_KEY, 300, function () {
                return Institution::with([
                    'specializations' => function($query) {
                        $query->select('specializations.id', 'specializations.name', 'qualification_id')
                            ->with(['qualification' => function($q) {
                                $q->select('id', 'qualification_name');
                            }]);
                    },
                    'collegeSpecializations' => function($query) {
                        $query->select('college_specializations.id', 'college_specializations.name', 'college_qualification_id')
                            ->with(['qualification' => function($q) {
                                $q->select('id', 'qualification_name');
                            }]);
                    },
                ])
                ->withCount(['reviews', 'likes'])
                ->withAvg('reviews', 'rating')
                ->get()
                ->toArray();
            });

            return response()->json(['data' => $institutions]);
        } catch (\Exception $e) {
            \Log::error('Error fetching institutions: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch institutions'], 500);
        }
    }

    public function show($id)
    {
        try {
            // Отзывы и лайки страницы «О вузе» получают отдельными эндпоинтами —
            // здесь они не нужны. БД удалённая, поэтому кэшируем готовый ответ.
            $institution = \Cache::remember("api.institutions.show.{$id}", 300, function () use ($id) {
                $institution = Institution::with([
                    'specializations' => function($query) {
                        $query->with(['qualification' => function($q) {
                            $q->select('id', 'qualification_name');
                        }]);
                    },
                ])->findOrFail($id);

                if ($institution->type === 'college') {
                    $institution->load(['collegeSpecializations' => function ($query) {
                        $query->select('college_specializations.id', 'college_specializations.name', 'college_qualification_id')
                            ->with(['qualification:id,qualification_name,description'])
                            ->withPivot('cost', 'duration');
                    }]);

                    // Устанавливаем специальности колледжа в свойство specializations
                    $institution->setRelation('specializations', $institution->collegeSpecializations);
                }

                return $institution->toArray();
            });

            return response()->json($institution);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Institution not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCurrentInstitution(Request $request)
    {
        try {
            $institution = Auth::guard('institution')->user();
            
            if (!$institution) {
                return response()->json(['error' => 'Не авторизован'], 401);
            }

            return response()->json([
                'institution' => $institution,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $credentials = $request->only('email', 'password');

            if (Auth::guard('institution')->attempt($credentials)) {
                $institution = Auth::guard('institution')->user();

                if ($institution->verified !== 'accepted') {
                    Auth::guard('institution')->logout();
                    return response()->json([
                        'error' => 'Ваш аккаунт еще не подтвержден'
                    ], 403);
                }

                // Создаем токен с указанием guard
                $token = $institution->createToken('institution-token', ['institution'])->plainTextToken;

                return response()->json([
                    'message' => 'Успешный вход',
                    'institution' => $institution,
                    'token' => $token
                ], 200);
            }

            return response()->json([
                'error' => 'Неверные учетные данные'
            ], 401);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:institutions,name',
                'email' => 'required|email|max:255|unique:institutions,email',
                'password' => 'required|string|min:6|confirmed',
                'location' => 'required|string|max:255',
                'phone' => 'required|string|max:50',
                'website' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'dormitory' => 'required|boolean',
                'grants' => 'required|boolean',
                'specializations' => 'required|array',
                'specializations.*' => 'exists:specializations,id',
            ]);
    
            DB::beginTransaction();
    
            // Создаем запись в institutions
            $institution = Institution::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Мутатор хеширует пароль
                'location' => $request->location,
                'phone' => $request->phone,
                'website' => $request->website,
                'address' => $request->address,
                'dormitory' => $request->dormitory,
                'grants' => $request->grants,
                'verified' => 'pending',
            ]);
    
            // Создаем запись в institution_applications
            $application = InstitutionApplication::create([
                'institution_id' => $institution->id,
                'institution_name' => $institution->name,
                'email' => $institution->email,
                'password' => $request->password, // Мутатор хеширует пароль
                'verified' => 'pending',
            ]);
    
            // Привязываем специальности
            if (!empty($request->specializations)) {
                $institution->specializations()->attach($request->specializations);
            }
    
            DB::commit();
    
            return response()->json([
                'message' => 'Университет успешно зарегистрирован и заявка отправлена на рассмотрение.',
                'institution' => $institution,
                'application' => $application,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLikedInstitutions()
    {
        try {
            $user = Auth::user();
            $likedInstitutions = Institution::whereHas('likes', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            return response()->json($likedInstitutions);
        } catch (\Exception $e) {
            \Log::error('Error fetching liked institutions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch liked institutions'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:institutions,name',
            'email' => 'required|email|max:255|unique:institutions,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $institution = Institution::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'verified' => 'pending',
        ]);

        return response()->json($institution, 201);
    }

    public function getReviews($institutionId)
    {
        $reviews = Review::where('institution_id', $institutionId)
            ->with('user')
            ->latest()
            ->get();

        return response()->json($reviews);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $institution = Institution::findOrFail($id);
        $institution->update($request->all());
        return response()->json($institution);
    }

    public function storeReview(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Пользователь не авторизован'], 401);
        }

        $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000'
        ]);

        $institution = Institution::findOrFail($id);

        $review = Review::create([
            'user_id' => $user->id,
            'institution_id' => $id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        \Cache::forget(self::INDEX_CACHE_KEY);

        $review->load('user');

        return response()->json($review, 201);
    }

    public function destroy($id)
    {
        Institution::destroy($id);
        return response()->json(null, 204);
    }

    public function like($id)
    {
        try {
            $institution = Institution::findOrFail($id);
            $user = Auth::user();

            // Проверяем, не лайкнул ли уже пользователь это учреждение
            $existingLike = Like::where('user_id', $user->id)
                ->where('institution_id', $id)
                ->first();

            if ($existingLike) {
                return response()->json(['message' => 'Already liked'], 400);
            }

            $like = new Like();
            $like->user_id = $user->id;
            $like->institution_id = $id;
            $like->save();

            \Cache::forget(self::INDEX_CACHE_KEY);

            return response()->json($like, 201);
        } catch (\Exception $e) {
            \Log::error('Error adding like: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to add like'], 500);
        }
    }

    public function unlike($id)
    {
        try {
            $user = Auth::user();
            $like = Like::where('user_id', $user->id)
                ->where('institution_id', $id)
                ->first();

            if (!$like) {
                return response()->json(['message' => 'Like not found'], 404);
            }

            $like->delete();
            \Cache::forget(self::INDEX_CACHE_KEY);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            \Log::error('Error removing like: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to remove like'], 500);
        }
    }
}