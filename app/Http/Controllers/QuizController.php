<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use App\Support\RandomDummyActivity;
use App\Support\QuizWeeklyParticipationRanking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class QuizController extends Controller
{
    private const DAILY_CREATION_LIMIT = 5;

    public function index(Request $request, QuizWeeklyParticipationRanking $weeklyRanking, HeartRewardManager $heartRewards)
    {
        $member = $this->quizCreator($request, false);
        $today = now(config('app.timezone'))->toDateString();
        $requestedSearch = $request->query('q', '');
        $search = is_string($requestedSearch) ? mb_substr(trim($requestedSearch), 0, 100) : '';
        $requestedQuestionCount = $request->query('questions', 0);
        $questionCount = is_scalar($requestedQuestionCount) ? (int) $requestedQuestionCount : 0;
        $questionCount = in_array($questionCount, [1, 2, 3], true) ? $questionCount : 0;
        $requestedSort = $request->query('sort');
        $sort = is_string($requestedSort) && in_array($requestedSort, ['latest', 'oldest', 'most_questions'], true)
            ? $requestedSort
            : 'latest';
        $idolCategories = [
            'boy' => ['individual', 'male'],
            'boy_group' => ['group', 'male'],
            'girl' => ['individual', 'female'],
            'girl_group' => ['group', 'female'],
        ];
        $requestedCategory = $request->query('idol_category');
        $idolCategory = is_string($requestedCategory) && isset($idolCategories[$requestedCategory])
            ? $requestedCategory
            : '';

        $query = DB::table('quizzes as quiz')
            ->whereNull('quiz.deleted_at')
            ->join('members as creator', 'creator.idx', '=', 'quiz.member_idx')
            ->leftJoin('idols as idol', 'idol.idx', '=', 'quiz.idol_idx')
            ->select('quiz.idx', 'quiz.title', 'quiz.member_idx', 'quiz.question_count', 'quiz.created_at',
                'quiz.idol_idx', 'quiz.cover_image_path', 'idol.name as idol_name', 'idol.type as idol_type',
                'creator.member_code as creator_member_code', 'creator.nickname as creator_nickname', 'creator.profile_image_path as creator_image_path',
                'creator.country_code as creator_country_code');

        $isAdmin = $member !== null && (int) $member->member_level === 10;
        $query->addSelect(['quiz_like_count' => DB::table('quiz_likes as quiz_like')
            ->selectRaw('COUNT(*)')->whereColumn('quiz_like.quiz_idx', 'quiz.idx')]);
        if ($isAdmin) {
            $query->addSelect([
                'quiz_view_count' => DB::table('member_access_logs as quiz_view')
                    ->selectRaw('COUNT(*)')->where('quiz_view.is_bot', 0)
                    ->whereRaw("quiz_view.request_path = CONCAT('/quizzes/', quiz.idx)"),
                'quiz_participant_count' => DB::table('quiz_attempts as quiz_attempt')
                    ->selectRaw('COUNT(DISTINCT quiz_attempt.member_idx)')
                    ->whereColumn('quiz_attempt.quiz_idx', 'quiz.idx')
                    ->whereColumn('quiz_attempt.member_idx', '<>', 'quiz.member_idx')
                    ->where('quiz_attempt.status', 'completed')->where('quiz_attempt.is_test_only', 0),
            ]);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('quiz.title', 'like', '%'.$search.'%')
                    ->orWhere('idol.name', 'like', '%'.$search.'%')
                    ->orWhere('idol.english_name', 'like', '%'.$search.'%')
                    ->orWhere('creator.nickname', 'like', '%'.$search.'%');
                $builder->orWhereExists(function ($localizedNames) use ($search) {
                    $localizedNames->selectRaw('1')->from('idol_localizations as quiz_search_localization')
                        ->whereColumn('quiz_search_localization.idol_idx', 'quiz.idol_idx')
                        ->where('quiz_search_localization.localized_name', 'like', '%'.$search.'%');
                })->orWhereExists(function ($aliases) use ($search) {
                    $aliases->selectRaw('1')->from('idol_name_aliases as quiz_search_alias')
                        ->whereColumn('quiz_search_alias.idol_idx', 'quiz.idol_idx')
                        ->where('quiz_search_alias.alias_name', 'like', '%'.$search.'%');
                });
            });
        }
        if ($questionCount > 0) {
            $query->where('quiz.question_count', $questionCount);
        }
        if ($idolCategory !== '') {
            [$idolType, $idolGender] = $idolCategories[$idolCategory];
            $query->where('idol.status', 'active')->where('idol.type', $idolType)->where('idol.gender', $idolGender);
        }

        match ($sort) {
            'oldest' => $query->orderBy('quiz.created_at')->orderBy('quiz.idx'),
            'most_questions' => $query->orderByDesc('quiz.question_count')->orderByDesc('quiz.created_at')->orderByDesc('quiz.idx'),
            default => $query->orderByDesc('quiz.created_at')->orderByDesc('quiz.idx'),
        };
        $quizzes = $query->paginate(12)->withQueryString();

        $questionCountTotals = DB::table('quizzes')->whereNull('deleted_at')
            ->select('question_count')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('question_count')
            ->pluck('total', 'question_count');
        $quizTotal = (int) DB::table('quizzes')->whereNull('deleted_at')->count();
        $categoryTotals = DB::table('quizzes as quiz')->join('idols as idol', 'idol.idx', '=', 'quiz.idol_idx')
            ->whereNull('quiz.deleted_at')
            ->where('idol.status', 'active')->select('idol.type', 'idol.gender')
            ->selectRaw('COUNT(*) AS total')->groupBy('idol.type', 'idol.gender')->get()
            ->mapWithKeys(fn (object $row): array => [
                ($row->gender === 'male' ? 'boy' : 'girl').($row->type === 'group' ? '_group' : '') => (int) $row->total,
            ]);
        $reward = DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')
            ->first(['is_enabled', 'min_heart_amount', 'max_heart_amount', 'heart_amount']);
        $rewardMin = (int) ($reward?->min_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardMax = (int) ($reward?->max_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardEnabled = (bool) ($reward?->is_enabled ?? false);
        $weekStart = QuizWeeklyParticipationRanking::currentWeekStart();
        $weekEnd = $weekStart->copy()->addWeek();
        $weeklyQuizRanking = $weeklyRanking->rankQuizzes($weekStart, $weekEnd);
        $monthStart = now('Asia/Seoul')->startOfMonth();
        $monthEnd = $monthStart->copy()->addMonth();
        $monthlyQuizRanking = $weeklyRanking->rankQuizzes($monthStart, $monthEnd);
        $weeklyQuizRewards = collect([1, 2, 3])->mapWithKeys(fn (int $rank): array => [
            $rank => $heartRewards->enabledAmount('quiz_weekly_rank_'.$rank),
        ]);

        $states = collect();
        $completedToday = collect();
        if ($member !== null && $quizzes->isNotEmpty()) {
            $attempts = DB::table('quiz_attempts')->where('member_idx', $member->idx)
                ->where('attempt_date', $today)->whereIn('quiz_idx', $quizzes->getCollection()->pluck('idx'))
                ->orderByDesc('idx')->get(['idx', 'quiz_idx', 'status']);
            $states = $attempts->unique('quiz_idx')->keyBy('quiz_idx');
            $completedToday = $attempts->where('status', 'completed')->pluck('quiz_idx')->flip();
        }

        $quizzes->getCollection()->transform(function (object $quiz) use ($member, $states, $completedToday): object {
            $quiz->is_owner = $member !== null && (int) $quiz->member_idx === (int) $member->idx;
            $quiz->attempt_status = $states->get($quiz->idx)->status ?? 'not_started';
            $quiz->completed_today = $member !== null && $completedToday->has($quiz->idx);
            $quiz->creator_image_url = \App\Support\ManagedImageStorage::publicUrl($quiz->creator_image_path);
            $quiz->cover_image_url = \App\Support\ManagedImageStorage::publicUrl($quiz->cover_image_path);
            $quiz->created_at_label = \Illuminate\Support\Carbon::parse($quiz->created_at)
                ->locale(app()->getLocale())->diffForHumans();
            return $quiz;
        });

        return view('quizzes.index', compact(
            'quizzes', 'member', 'isAdmin', 'search', 'questionCount', 'sort', 'questionCountTotals',
            'quizTotal', 'categoryTotals', 'idolCategory', 'rewardMin', 'rewardMax', 'rewardEnabled',
            'weeklyQuizRanking', 'weeklyQuizRewards', 'weekStart', 'weekEnd',
            'monthlyQuizRanking', 'monthStart', 'monthEnd'
        ));
    }

    public function create(Request $request, HeartRewardManager $heartRewards)
    {
        return $this->renderForm($request, $heartRewards);
    }

    public function edit(Request $request, int $idx, HeartRewardManager $heartRewards)
    {
        $member = $this->quizCreator($request);
        $quiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->first();
        abort_if($quiz === null, 404);
        $this->authorizeQuizEdit($member, $quiz);

        $request->attributes->set('quizEditing', $quiz);
        $request->attributes->set('quizEditingQuestions', DB::table('quiz_questions')
            ->where('quiz_idx', $idx)->where('quiz_version', $quiz->quiz_version)
            ->orderBy('question_order')->get()->map(function (object $question): object {
                $question->choices = DB::table('quiz_question_choices')->where('question_idx', $question->idx)
                    ->orderBy('choice_order')->pluck('choice_text')->all();
                $question->existing_question_idx = (int) $question->idx;
                $question->type = $question->question_type;
                $question->correct_answer = $question->question_type === 'ox'
                    ? ((int) $question->correct_choice_order === 1 ? 'O' : 'X') : null;
                return $question;
            })->all());

        return $this->renderForm($request, $heartRewards);
    }

    private function renderForm(Request $request, HeartRewardManager $heartRewards)
    {
        $member = $this->quizCreator($request);
        $editingQuiz = $request->attributes->get('quizEditing');
        $existingQuestions = $request->attributes->get('quizEditingQuestions', []);
        $alert = $request->session()->pull('quiz_alert');
        $idolCategories = [
            'boy' => ['individual', 'male'],
            'boy_group' => ['group', 'male'],
            'girl' => ['individual', 'female'],
            'girl_group' => ['group', 'female'],
        ];
        $oldIdolCategory = old('idol_category', $editingQuiz ? $this->idolCategoryFor($editingQuiz->idol_idx) : '');
        $selectedIdolCategory = is_string($oldIdolCategory) && isset($idolCategories[$oldIdolCategory]) ? $oldIdolCategory : '';
        $oldIdolIdx = old('idol_idx', $editingQuiz?->idol_idx);
        $selectedIdolIdx = is_scalar($oldIdolIdx) && ctype_digit((string) $oldIdolIdx) ? (int) $oldIdolIdx : null;
        $selectedIdol = null;
        if ($selectedIdolCategory !== '' && $selectedIdolIdx !== null) {
            [$idolType, $idolGender] = $idolCategories[$selectedIdolCategory];
            $selectedIdol = DB::table('idols')->where('idx', $selectedIdolIdx)
                ->where('status', 'active')->where('type', $idolType)->where('gender', $idolGender)
                ->first(['idx', 'name', 'type']);
        }
        $creatorRewardSettings = $this->creatorRewardSettings();
        $randomDummyMode = $editingQuiz === null && $this->quizDummyMode($request);
        $creationQuota = $editingQuiz === null && ! $randomDummyMode ? $this->quizCreationQuota((int) $member->idx) : null;
        $randomRequestKey = old('request_key');
        if (! is_string($randomRequestKey) || ! preg_match('/^[A-Fa-f0-9]{64}$/', $randomRequestKey)) {
            $randomRequestKey = $randomDummyMode ? bin2hex(random_bytes(32)) : null;
        }
        $reward = DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')
            ->first(['is_enabled', 'min_heart_amount', 'max_heart_amount', 'heart_amount']);
        $rewardMin = (int) ($reward?->min_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardMax = (int) ($reward?->max_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardEnabled = (bool) ($reward?->is_enabled ?? false);
        $approvalRewardAmount = $heartRewards->enabledAmount('idol_registration_approved');

        return view('quizzes.create', compact(
            'member', 'alert', 'rewardMin', 'rewardMax', 'rewardEnabled', 'editingQuiz', 'existingQuestions',
            'selectedIdolCategory', 'selectedIdol',
            'creatorRewardSettings', 'approvalRewardAmount', 'creationQuota', 'randomDummyMode', 'randomRequestKey'
        ));
    }

    public function update(Request $request, int $idx)
    {
        $member = $this->quizCreator($request);
        $quiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->first();
        abort_if($quiz === null, 404);
        $this->authorizeQuizEdit($member, $quiz);

        $categoryParts = [
            'boy' => ['individual', 'male'], 'boy_group' => ['group', 'male'],
            'girl' => ['individual', 'female'], 'girl_group' => ['group', 'female'],
        ];
        $basicInfo = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'idol_category' => ['required', Rule::in(array_keys($categoryParts))],
            'idol_idx' => ['required', 'integer', 'min:1'],
            'quiz_version' => ['required', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_cover_image' => ['nullable', 'boolean'],
        ]);
        $basicInfo->after(function ($validator) use ($request, $categoryParts, $quiz): void {
            if ((int) $request->input('quiz_version') !== (int) $quiz->quiz_version) {
                $validator->errors()->add('quiz_version', __('quiz.edit_conflict'));
            }
            $category = $request->input('idol_category');
            $idolIdx = $request->input('idol_idx');
            if (! is_string($category) || ! isset($categoryParts[$category])
                || ! is_scalar($idolIdx) || ! ctype_digit((string) $idolIdx)) return;
            [$type, $gender] = $categoryParts[$category];
            if (! DB::table('idols')->where('idx', (int) $idolIdx)->where('status', 'active')
                ->where('type', $type)->where('gender', $gender)->exists()) {
                $validator->errors()->add('idol_idx', __('quiz.idol_selection_invalid'));
            }
        });
        if ($basicInfo->fails()) {
            $message = $basicInfo->errors()->has('quiz_version') ? __('quiz.edit_conflict') : __('quiz.validation_failed');
            return back()->withInput()->with('quiz_alert', $message);
        }

        $idolIdx = (int) $basicInfo->validated()['idol_idx'];
        try {
            $questions = $this->validatedQuestions($request, $idx, (int) $quiz->quiz_version);
        } catch (ValidationException) {
            return back()->withInput()->with('quiz_alert', __('quiz.validation_failed'));
        }

        $imagePaths = [];
        try {
            $coverImagePath = $quiz->cover_image_path;
            if ($request->boolean('remove_cover_image')) $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $storedPath = $request->file('cover_image')->store('quizzes/covers', 'public');
                if (! is_string($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
                    if (is_string($storedPath)) Storage::disk('public')->delete($storedPath);
                    throw ValidationException::withMessages(['cover_image' => __('quiz.image_upload_failed')]);
                }
                $coverImagePath = 'storage/'.$storedPath;
                $imagePaths[] = $coverImagePath;
            }
            foreach ($questions as $index => &$question) {
                if ($question['type'] !== 'image') {
                    $question['image_path'] = null;
                    continue;
                }
                if ($request->hasFile("questions.$index.image")) {
                    $storedPath = $request->file("questions.$index.image")->store('quizzes', 'public');
                    if (! is_string($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
                        if (is_string($storedPath)) Storage::disk('public')->delete($storedPath);
                        throw ValidationException::withMessages(['questions.'.$index.'.image' => __('quiz.image_upload_failed')]);
                    }
                    $question['image_path'] = 'storage/'.$storedPath;
                    $imagePaths[] = $question['image_path'];
                }
            }
            unset($question);

            DB::transaction(function () use ($request, $quiz, $member, $idolIdx, $questions, $coverImagePath, $basicInfo): void {
                $lockedQuiz = DB::table('quizzes')->where('idx', $quiz->idx)->whereNull('deleted_at')->lockForUpdate()->first();
                abort_if($lockedQuiz === null, 404);
                abort_unless((int) $lockedQuiz->quiz_version === (int) $quiz->quiz_version, 409, __('quiz.edit_conflict'));
                $this->authorizeQuizEdit($member, $lockedQuiz);
                if (! DB::table('idols')->where('idx', $idolIdx)->where('status', 'active')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['idol_idx' => __('quiz.idol_selection_invalid')]);
                }

                $nextVersion = (int) $lockedQuiz->quiz_version + 1;
                $now = now();
                DB::table('quizzes')->where('idx', $quiz->idx)->update([
                    'title' => trim($basicInfo->validated()['title']),
                    'actor_member_idx' => (int) $member->idx,
                    'idol_idx' => $idolIdx,
                    'cover_image_path' => $coverImagePath,
                    'question_count' => count($questions),
                    'quiz_version' => $nextVersion,
                    'updated_at' => $now,
                ]);
                $this->insertQuestionVersion((int) $quiz->idx, $nextVersion, $questions, $now);
            });
        } catch (ValidationException) {
            $this->deleteUploadedImages($imagePaths);
            return back()->withInput()->with('quiz_alert', __('quiz.validation_failed'));
        } catch (HttpException $exception) {
            $this->deleteUploadedImages($imagePaths);
            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteUploadedImages($imagePaths);
            report($exception);
            return back()->withInput()->with('quiz_alert', __('quiz.update_failed'));
        }

        return redirect()->route('quizzes.show', $idx)->with('quiz_updated', true);
    }

    public function store(Request $request)
    {
        $member = $this->quizCreator($request);
        $randomActivities = app(RandomDummyActivity::class);
        $randomDummyMode = $this->quizDummyMode($request);
        $randomRequestKey = $request->input('request_key');
        $actorIdx = (int) $member->idx;
        $categoryParts = [
            'boy' => ['individual', 'male'],
            'boy_group' => ['group', 'male'],
            'girl' => ['individual', 'female'],
            'girl_group' => ['group', 'female'],
        ];
        $basicInfo = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'idol_category' => ['required', Rule::in(array_keys($categoryParts))],
            'idol_idx' => ['required', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $basicInfo->after(function ($validator) use ($request, $categoryParts): void {
            $category = $request->input('idol_category');
            $idolIdx = $request->input('idol_idx');
            if (! is_string($category) || ! isset($categoryParts[$category])
                || ! is_scalar($idolIdx) || ! ctype_digit((string) $idolIdx)) {
                return;
            }
            [$type, $gender] = $categoryParts[$category];
            if (! DB::table('idols')->where('idx', (int) $idolIdx)->where('status', 'active')
                ->where('type', $type)->where('gender', $gender)->exists()) {
                $validator->errors()->add('idol_idx', __('quiz.idol_selection_invalid'));
            }
        });
        if ($basicInfo->fails()) {
            return back()->withInput()->with('quiz_alert', __('quiz.validation_failed'));
        }
        $idolIdx = (int) $basicInfo->validated()['idol_idx'];
        try {
            $questions = $this->validatedQuestions($request);
        } catch (ValidationException) {
            return back()->withInput()->with('quiz_alert', __('quiz.validation_failed'));
        }
        $imagePaths = [];
        $reservation = null;

        try {
            $payload = $this->quizSubmissionPayload($request, $basicInfo->validated(), $questions);
            $existingReservation = is_string($randomRequestKey) && preg_match('/^[A-Fa-f0-9]{64}$/', $randomRequestKey)
                ? $randomActivities->find($request, 'quiz_create', $randomRequestKey, $payload)
                : null;
            $randomDummyMode = $randomDummyMode || $existingReservation !== null;
            if ($randomDummyMode) {
                $request->validate(['request_key' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/']]);
                $reservation = $randomActivities->reserve($request, 'quiz_create', $randomRequestKey, $payload, false,
                    fn (): ?object => $this->eligibleQuizCreatorDummy(), true);
                if ($reservation?->replay) {
                    abort_if($reservation->result_type !== 'quiz' || $reservation->result_idx === null, 409);
                    return redirect()->route('quizzes.show', $reservation->result_idx);
                }
            }

            $coverImagePath = null;
            if ($request->hasFile('cover_image')) {
                $storedPath = $request->file('cover_image')->store('quizzes/covers', 'public');
                if (! is_string($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
                    if (is_string($storedPath)) Storage::disk('public')->delete($storedPath);
                    throw ValidationException::withMessages(['cover_image' => __('quiz.image_upload_failed')]);
                }
                $coverImagePath = 'storage/'.$storedPath;
                $imagePaths[] = $coverImagePath;
            }
            foreach ($questions as $index => &$question) {
                if ($question['type'] !== 'image') {
                    $question['image_path'] = null;
                    continue;
                }
                $storedPath = $request->file("questions.$index.image")->store('quizzes', 'public');
                if (! is_string($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
                    if (is_string($storedPath)) Storage::disk('public')->delete($storedPath);
                    throw ValidationException::withMessages(['questions.'.$index.'.image' => __('quiz.image_upload_failed')]);
                }
                $question['image_path'] = 'storage/'.$storedPath;
                $imagePaths[] = $question['image_path'];
            }
            unset($question);

            $created = DB::transaction(function () use ($request, $questions, $member, $actorIdx, $idolIdx, $coverImagePath, $basicInfo, $randomActivities, $reservation): array {
                $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
                if ($reservation?->replay) {
                    return ['idx' => (int) $reservation->result_idx, 'replay' => true];
                }
                $creatorIdx = (int) $member->idx;
                if ($reservation !== null) {
                    $creator = DB::table('members')->where('idx', $reservation->member_idx)
                        ->where('member_level', 1)->where('is_dummy', 1)->where('status', 'active')
                        ->whereNull('deleted_at')->lockForUpdate()->first(['idx']);
                    abort_if($creator === null, 422, __('quiz.random_dummy_no_eligible'));
                    $creatorIdx = (int) $creator->idx;
                }

                // Serializes the selected member's quota check with any other creation request.
                $lockedMember = DB::table('members')->where('idx', $creatorIdx)
                    ->whereIn('member_level', [1, 10])->where('status', 'active')->whereNull('deleted_at')
                    ->when($reservation === null, fn ($query) => $query->where('is_dummy', 0), fn ($query) => $query->where('is_dummy', 1))
                    ->lockForUpdate()->first(['idx']);
                abort_if($lockedMember === null, 403);

                if ($this->todayQuizCreationCount($creatorIdx) >= self::DAILY_CREATION_LIMIT) {
                    throw ValidationException::withMessages([
                        'quiz_creation_limit' => __('quiz.daily_creation_limit_reached', [
                            'limit' => self::DAILY_CREATION_LIMIT,
                            'remaining' => 0,
                        ]),
                    ]);
                }

                $activeIdol = DB::table('idols')->where('idx', $idolIdx)->where('status', 'active')->lockForUpdate()->exists();
                if (! $activeIdol) {
                    throw ValidationException::withMessages(['idol_idx' => __('quiz.idol_selection_invalid')]);
                }
                $now = now();
                $quizIdx = (int) DB::table('quizzes')->insertGetId([
                    'member_idx' => $creatorIdx,
                    'actor_member_idx' => $actorIdx,
                    'title' => trim($basicInfo->validated()['title']),
                    'idol_idx' => $idolIdx,
                    'cover_image_path' => $coverImagePath,
                    'question_count' => count($questions),
                    'quiz_version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->insertQuestionVersion($quizIdx, 1, $questions, $now);
                DB::table('quiz_creation_histories')->insert([
                    'member_idx' => $creatorIdx,
                    'quiz_idx' => $quizIdx,
                    'created_at' => now('UTC'),
                ]);
                if ($reservation !== null) {
                    $randomActivities->complete($reservation, 'quiz', $quizIdx);
                    $randomActivities->log($request, $reservation, 'quiz_created', '관리자 Random Dummy Mode로 더미 회원 명의 퀴즈를 생성했습니다.');
                }

                return ['idx' => $quizIdx, 'replay' => false];
            });
            if ($created['replay']) $this->deleteUploadedImages($imagePaths);
        } catch (ValidationException $exception) {
            $this->deleteUploadedImages($imagePaths);
            $message = isset($exception->errors()['quiz_creation_limit'])
                ? __('quiz.daily_creation_limit_reached', ['limit' => self::DAILY_CREATION_LIMIT, 'remaining' => 0])
                : (isset($exception->errors()['random_dummy']) ? __('quiz.random_dummy_no_eligible') : __('quiz.validation_failed'));
            return back()->withInput()->with('quiz_alert', $message);
        } catch (HttpException $exception) {
            $this->deleteUploadedImages($imagePaths);
            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteUploadedImages($imagePaths);
            report($exception);
            return back()->withInput()->with('quiz_alert', __('quiz.create_failed'));
        }

        return redirect()->route('quizzes.show', $created['idx'])->with('quiz_created', ! $created['replay']);
    }

    public function show(Request $request, int $idx)
    {
        $quiz = DB::table('quizzes as quiz')->join('members as creator', 'creator.idx', '=', 'quiz.member_idx')
            ->leftJoin('idols as idol', 'idol.idx', '=', 'quiz.idol_idx')
            ->where('quiz.idx', $idx)->whereNull('quiz.deleted_at')
            ->first(['quiz.idx', 'quiz.title', 'quiz.member_idx', 'quiz.idol_idx', 'quiz.cover_image_path', 'quiz.question_count', 'quiz.quiz_version', 'quiz.created_at',
                'creator.member_code as creator_member_code', 'creator.nickname as creator_nickname', 'creator.country_code as creator_country_code', 'creator.profile_image_path as creator_image_path',
                'idol.name as idol_name', 'idol.type as idol_type', 'idol.gender as idol_gender', 'idol.status as idol_status']);
        abort_if($quiz === null, 404);

        $actor = $request->attributes->get('currentMember');
        $member = $this->quizCreator($request, false);
        $randomActivities = app(RandomDummyActivity::class);
        $randomDummyMode = $this->quizDummyMode($request);
        $requestedKey = $request->query('request_key');
        if (! $randomDummyMode && (int) ($actor?->member_level ?? 0) === 10
            && is_string($requestedKey) && preg_match('/^[A-Fa-f0-9]{64}$/', $requestedKey)) {
            $randomDummyMode = DB::table('admin_dummy_activity_requests')->where('actor_member_idx', $actor->idx)
                ->where('action_code', 'quiz_participation')->where('request_key', $requestedKey)->exists();
        }
        $randomRequestKey = null;
        $randomRetryRequestKey = null;
        $randomReservation = null;
        $attempt = null;
        if ($randomDummyMode && $actor !== null) {
            $requestKey = $request->query('request_key');
            $reservationQuery = DB::table('admin_dummy_activity_requests as reservation')
                ->leftJoin('quiz_attempts as attempt', function ($join) use ($idx): void {
                    $join->on('attempt.idx', '=', 'reservation.result_idx')->where('attempt.quiz_idx', '=', $idx);
                })
                ->where('reservation.actor_member_idx', $actor->idx)
                ->where('reservation.action_code', 'quiz_participation');
            if (is_string($requestKey) && preg_match('/^[A-Fa-f0-9]{64}$/', $requestKey)) {
                $reservationQuery->where('reservation.request_key', $requestKey);
            } else {
                $reservationQuery->where('reservation.result_type', 'quiz_attempt')
                    ->where('attempt.quiz_idx', $idx)->orderByDesc('reservation.idx');
            }
            $randomReservation = $reservationQuery->first([
                'reservation.idx', 'reservation.actor_member_idx', 'reservation.member_idx', 'reservation.action_code',
                'reservation.request_key', 'reservation.result_type', 'reservation.result_idx', 'reservation.completed_at',
                'attempt.quiz_idx as attempt_quiz_idx', 'attempt.quiz_version as attempt_quiz_version', 'attempt.status as attempt_status',
            ]);
            if ($randomReservation !== null && $randomReservation->result_type === null) {
                $randomRequestKey = $randomReservation->request_key;
                $randomReservation->replay = false;
                $member = $randomActivities->member($randomReservation);
            } elseif ($randomReservation !== null && $randomReservation->result_type === 'quiz_attempt'
                && (int) $randomReservation->attempt_quiz_idx === $idx) {
                $randomRequestKey = $randomReservation->request_key;
                $randomReservation->replay = $randomReservation->completed_at !== null;
                $member = $randomActivities->member($randomReservation);
                $attempt = DB::table('quiz_attempts')->where('idx', $randomReservation->result_idx)->first();
            } elseif ($randomReservation !== null && $randomReservation->result_type !== null) {
                abort(409, __('quiz.random_dummy_request_conflict'));
            }
            if ($member !== null) {
                $request->attributes->set('activityMember', $member);
                $request->attributes->set('selectedActivityMember', $member);
                $request->attributes->set('activityDummyMode', true);
            }
            $randomRequestKey ??= bin2hex(random_bytes(32));
            $randomRetryRequestKey = bin2hex(random_bytes(32));
            // An administrator acting through Random Dummy Mode plays using the reserved member identity.
            if ($member === null && $randomReservation === null) $member = null;
        }
        $isOwner = $member !== null && (int) $member->idx === (int) $quiz->member_idx;
        $canReplayDummy = $randomDummyMode
            && (int) ($actor?->member_level ?? 0) === 10
            && (int) ($actor?->is_dummy ?? 1) === 0
            && $randomReservation?->result_type === 'quiz_attempt'
            && (bool) ($randomReservation->replay ?? false);
        $canEdit = $actor !== null && ($isOwner || (int) $actor->member_level === 10);
        $ownerRetryToken = $request->query('owner_retry_token');
        $sessionRetryToken = session('quiz_retry_tokens.'.$idx);
        $ownerRetryToken = $isOwner && is_string($ownerRetryToken) && is_string($sessionRetryToken)
            && hash_equals($sessionRetryToken, $ownerRetryToken) ? $ownerRetryToken : null;
        $today = now(config('app.timezone'))->toDateString();
        if (! $randomDummyMode) {
            $attempt = $member === null ? null : DB::table('quiz_attempts')
            ->where('quiz_idx', $idx)->where('member_idx', $member->idx)->where('attempt_date', $today)
            ->orderByDesc('idx')->first();
        }
        if ($ownerRetryToken !== null && $attempt?->status === 'completed') $attempt = null;
        $state = $attempt->status ?? 'not_started';
        $canParticipate = ($randomDummyMode && (int) ($actor?->member_level ?? 0) === 10)
            || ($member !== null && (
            ((int) $member->member_level === 1 && (int) $member->is_dummy === 0)
            || ($isOwner && (int) $member->is_dummy === 0)
        ));
        $displayQuestionCount = $state === 'in_progress' ? (int) $attempt->question_count : (int) $quiz->question_count;

        $questionVersion = (int) ($attempt->quiz_version ?? $quiz->quiz_version);
        $questions = DB::table('quiz_questions')->where('quiz_idx', $idx)->where('quiz_version', $questionVersion)->orderBy('question_order')
            ->get(['idx', 'question_order', 'question_type', 'prompt', 'image_path'])
            ->map(function (object $question): object {
                $question->choices = DB::table('quiz_question_choices')->where('question_idx', $question->idx)
                    ->orderBy('choice_order')->get(['choice_order', 'choice_text']);
                return $question;
            });

        $resultQuestions = collect();
        if ($state === 'completed') $resultQuestions = $this->completedResult((int) $attempt->idx);
        $quizLikeCount = (int) DB::table('quiz_likes')->where('quiz_idx', $idx)->count();
        $showQuizStatistics = $actor !== null && (int) $actor->member_level === 10 && (int) $actor->is_dummy === 0;
        $quizViewCount = 0;
        $quizParticipantCount = 0;
        if ($showQuizStatistics) {
            $quizViewCount = (int) DB::table('member_access_logs as quiz_view')
                ->where('quiz_view.is_bot', 0)
                ->whereRaw("quiz_view.request_path = CONCAT('/quizzes/', ?)", [$idx])
                ->count();
            $quizParticipantCount = (int) DB::table('quiz_attempts as quiz_attempt')
                ->where('quiz_attempt.quiz_idx', $idx)
                ->where('quiz_attempt.member_idx', '<>', $quiz->member_idx)
                ->where('quiz_attempt.status', 'completed')
                ->where('quiz_attempt.is_test_only', 0)
                ->distinct('quiz_attempt.member_idx')->count('quiz_attempt.member_idx');
        }
        $quizLiked = $member !== null && DB::table('quiz_likes')->where('quiz_idx', $idx)
            ->where('member_idx', $member->idx)->exists();
        $quizLikeQuestionVersion = (int) ($attempt?->quiz_version ?? $quiz->quiz_version);

        $reward = DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')
            ->first(['is_enabled', 'min_heart_amount', 'max_heart_amount', 'heart_amount']);
        $rewardMin = (int) ($reward?->min_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardMax = (int) ($reward?->max_heart_amount ?? $reward?->heart_amount ?? 0);
        $rewardEnabled = (bool) ($reward?->is_enabled ?? false);
        $creatorRewardSettings = $this->creatorRewardSettings();

        return view('quizzes.show', compact(
            'quiz', 'member', 'isOwner', 'canEdit', 'canParticipate', 'state', 'attempt', 'questions',
            'resultQuestions', 'rewardMin', 'rewardMax', 'rewardEnabled', 'creatorRewardSettings', 'questionVersion', 'displayQuestionCount',
            'randomDummyMode', 'randomRequestKey', 'randomRetryRequestKey', 'ownerRetryToken', 'canReplayDummy',
            'quizLikeCount', 'quizLiked', 'quizLikeQuestionVersion', 'showQuizStatistics', 'quizViewCount',
            'quizParticipantCount', 'actor'
        ));
    }

    public function toggleLike(Request $request, int $idx)
    {
        $actor = $request->attributes->get('currentMember');
        abort_if($actor === null, 403);
        $validated = $request->validate([
            'liked' => ['required', 'boolean'],
            'request_key' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'quiz_version' => ['required_with:request_key', 'integer', 'min:1'],
        ]);

        $reservation = null;
        if (isset($validated['request_key'])) {
            abort_unless((int) $actor->member_level === 10 && (int) $actor->is_dummy === 0, 403);
            $reservation = app(RandomDummyActivity::class)->find($request, 'quiz_participation', $validated['request_key'], [
                'quiz_idx' => $idx,
                'quiz_version' => (int) $validated['quiz_version'],
            ]);
            abort_if($reservation === null || $reservation->result_type !== 'quiz_attempt' || $reservation->result_idx === null, 409);
            $linkedAttempt = DB::table('quiz_attempts')->where('idx', $reservation->result_idx)
                ->where('quiz_idx', $idx)->where('member_idx', $reservation->member_idx)
                ->where('actor_member_idx', $actor->idx)->where('status', 'completed')->exists();
            abort_unless($linkedAttempt, 409);
        }

        $desiredLikeState = (bool) $validated['liked'];
        $likeState = DB::transaction(function () use ($idx, $actor, $reservation, $desiredLikeState): array {
            $quiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first(['idx']);
            abort_if($quiz === null, 404);

            $targetMemberIdx = (int) ($reservation?->member_idx ?? $actor->idx);
            $memberQuery = DB::table('members')->where('idx', $targetMemberIdx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at');
            if ($reservation !== null) $memberQuery->where('member_level', 1)->where('is_dummy', 1);
            abort_if($memberQuery->lockForUpdate()->first(['idx']) === null, 403);

            if ($desiredLikeState) {
                DB::table('quiz_likes')->insertOrIgnore([
                    'quiz_idx' => $idx,
                    'member_idx' => $targetMemberIdx,
                    'actor_member_idx' => (int) $actor->idx,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('quiz_likes')->where('quiz_idx', $idx)->where('member_idx', $targetMemberIdx)->delete();
            }

            return [
                'liked' => DB::table('quiz_likes')->where('quiz_idx', $idx)->where('member_idx', $targetMemberIdx)->exists(),
                'count' => (int) DB::table('quiz_likes')->where('quiz_idx', $idx)->count(),
            ];
        }, 3);

        return response()->json(['success' => true, 'data' => $likeState]);
    }

    public function destroy(Request $request, int $idx)
    {
        $member = $this->quizCreator($request);
        DB::transaction(function () use ($member, $idx): void {
            $currentMember = DB::table('members')->where('idx', $member->idx)
                ->whereIn('member_level', [1, 10])->where('is_dummy', 0)
                ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()
                ->first(['idx', 'member_level']);
            abort_if($currentMember === null, 403);
            $quiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if($quiz === null, 404);
            $this->authorizeQuizEdit($currentMember, $quiz);

            DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('quizzes.index')->with('global_notification', [
            'title' => __('quiz.delete_quiz'),
            'message' => __('quiz.deleted_message'),
            'type' => 'success',
        ]);
    }

    public function answer(Request $request, int $idx, HeartRewardManager $rewards)
    {
        $quiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')
            ->first(['idx', 'member_idx', 'question_count', 'quiz_version']);
        abort_if($quiz === null, 404);
        $actor = $request->attributes->get('currentMember');
        $randomActivities = app(RandomDummyActivity::class);
        $requestKey = $request->input('request_key');
        $randomMode = $this->quizDummyMode($request);
        $reservation = null;
        if ($randomMode || ((int) ($actor?->member_level ?? 0) === 10 && is_string($requestKey)
            && preg_match('/^[A-Fa-f0-9]{64}$/', $requestKey))) {
            $randomValidator = Validator::make($request->all(), [
                'request_key' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
                'quiz_version' => ['required', 'integer', 'min:1'],
            ]);
            if ($randomValidator->fails()) return back()->withErrors($randomValidator);
            $randomInput = $randomValidator->validated();
            $payload = ['quiz_idx' => $idx, 'quiz_version' => (int) $randomInput['quiz_version']];
            $existingReservation = $randomActivities->find($request, 'quiz_participation', $requestKey, $payload);
            $randomMode = $randomMode || $existingReservation !== null;
            if ($randomMode) {
                $reservation = $randomActivities->reserve($request, 'quiz_participation', $requestKey, $payload, false,
                    fn (): ?object => $this->eligibleQuizParticipantDummy($idx, (int) $quiz->member_idx), true);
                abort_if($reservation === null, 422, __('quiz.random_dummy_no_eligible'));
                if ($reservation->replay) {
                    return redirect()->route('quizzes.show', ['idx' => $idx, 'request_key' => $requestKey]);
                }
                $member = $randomActivities->member($reservation);
                abort_if($member === null, 422, __('quiz.random_dummy_no_eligible'));
                $request->attributes->set('activityMember', $member);
                $request->attributes->set('selectedActivityMember', $member);
                $request->attributes->set('activityDummyMode', true);
            } else {
                $member = $actor;
            }
        } else {
            $member = $actor;
        }
        $isOwner = $member !== null && (int) $quiz->member_idx === (int) $member->idx;
        $isOrdinaryMember = $member !== null && (int) $member->member_level === 1 && (int) $member->is_dummy === 0;
        $isEligibleCreator = $isOwner && in_array((int) $member->member_level, [1, 10], true) && (int) $member->is_dummy === 0;
        abort_unless($randomMode || $isOrdinaryMember || $isEligibleCreator, 403);
        if ($request->boolean('restart_attempt')) abort_unless($randomMode || $isEligibleCreator, 403);

        if ($randomMode && $request->boolean('restart_attempt')) {
            $this->startRandomQuizAttempt($request, $idx, $quiz, $actor, $reservation, $randomActivities);
            return redirect()->route('quizzes.show', ['idx' => $idx, 'request_key' => $requestKey]);
        }

        if ($isOwner && $request->boolean('restart_attempt')) {
            $restartValidator = Validator::make($request->all(), ['quiz_version' => ['required', 'integer', 'min:1']]);
            if ($restartValidator->fails()) return back()->withErrors($restartValidator);
            $retryToken = bin2hex(random_bytes(32));
            DB::transaction(function () use ($idx, $member, $restartValidator): void {
                $lockedQuiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first();
                abort_if($lockedQuiz === null, 404);
                abort_unless((int) $restartValidator->validated()['quiz_version'] === (int) $lockedQuiz->quiz_version, 409, __('quiz.edit_conflict'));
                $lockedMember = DB::table('members')->where('idx', $member->idx)->whereIn('member_level', [1, 10])
                    ->where('is_dummy', 0)->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx']);
                abort_if($lockedMember === null || (int) $lockedQuiz->member_idx !== (int) $member->idx, 403);
            });

            session()->put('quiz_retry_tokens.'.$idx, $retryToken);
            return redirect()->route('quizzes.show', ['idx' => $idx, 'owner_retry_token' => $retryToken]);
        }

        $answerValidator = Validator::make($request->all(), [
            'question_order' => ['required_without:answers', 'integer', 'min:1', 'max:3'],
            'selected_choice_order' => ['required_without:answers', 'integer', 'min:1', 'max:5'],
            'answers' => ['sometimes', 'required', 'array'],
            'answers.*' => ['required', 'integer', 'min:1', 'max:5'],
            'quiz_version' => ['nullable', 'integer', 'min:1'],
            'owner_retry_token' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);
        if ($answerValidator->fails()) return back()->withErrors($answerValidator);
        $validated = $answerValidator->validated();
        $hasAnswerBundle = array_key_exists('answers', $validated);
        $finalSubmission = $hasAnswerBundle || (int) $quiz->question_count === 1;
        if (! $finalSubmission && (int) $quiz->question_count > 1) {
            return back()->withErrors(['answers' => __('quiz.answer_invalid')]);
        }
        $answerSelections = $hasAnswerBundle
            ? array_map('intval', $validated['answers'])
            : [1 => (int) $validated['selected_choice_order']];
        $ownerRetryToken = $validated['owner_retry_token'] ?? null;
        $ownerRetryAllowed = $isOwner && is_string($ownerRetryToken)
            && is_string(session('quiz_retry_tokens.'.$idx))
            && hash_equals((string) session('quiz_retry_tokens.'.$idx), $ownerRetryToken);
        abort_if($ownerRetryToken !== null && ! $ownerRetryAllowed, 409, __('quiz.attempt_unavailable'));

        $today = now(config('app.timezone'))->toDateString();
        DB::transaction(function () use ($request, $idx, $quiz, $member, $actor, $isOwner, $ownerRetryAllowed, $randomMode, $requestKey, $reservation, $randomActivities, $today, $answerSelections, $rewards): void {
            $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
            if ($reservation?->replay) return;
            // The quiz row lock serializes daily-limit checks and all attempt updates for this quiz.
            $participantIdx = $reservation?->member_idx ?? (int) $member->idx;
            $lockedMember = DB::table('members')->where('idx', $participantIdx)->whereIn('member_level', [1, 10])
                ->where('is_dummy', $reservation ? 1 : 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'member_level', 'is_dummy']);
            abort_if($lockedMember === null, 403);
            $lockedQuiz = DB::table('quizzes')->where('idx', $idx)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if($lockedQuiz === null, 404);
            abort_unless($randomMode || (int) $lockedMember->member_level === 1 || (int) $lockedQuiz->member_idx === (int) $member->idx, 403);

            $attempt = DB::table('quiz_attempts')->where('quiz_idx', $idx)->where('member_idx', $member->idx)
                ->where('attempt_date', $today)->orderByDesc('idx')->lockForUpdate()->first();
            if ($attempt !== null && $attempt->status === 'completed') {
                if ($ownerRetryAllowed || ($reservation !== null && $isOwner)) $attempt = null;
                else {
                    abort_if($reservation !== null, 422, __('quiz.random_dummy_no_eligible'));
                    return;
                }
            }

            $questionVersion = (int) ($attempt->quiz_version ?? $lockedQuiz->quiz_version);
            $expectedQuestionCount = (int) ($attempt->question_count ?? $lockedQuiz->question_count);
            if ($attempt === null && $request->filled('quiz_version')) {
                abort_unless((int) $request->input('quiz_version') === $questionVersion, 409, __('quiz.edit_conflict'));
            }
            $orders = array_map('intval', array_keys($answerSelections));
            sort($orders);
            abort_unless($orders === range(1, $expectedQuestionCount), 422, __('quiz.answer_invalid'));
            $questionsByOrder = DB::table('quiz_questions')->where('quiz_idx', $idx)->where('quiz_version', $questionVersion)
                ->whereIn('question_order', $orders)->get(['idx', 'question_order'])->keyBy('question_order');
            foreach ($answerSelections as $questionOrder => $selectedChoiceOrder) {
                $question = $questionsByOrder->get((int) $questionOrder);
                abort_if($question === null || ! DB::table('quiz_question_choices')->where('question_idx', $question->idx)
                    ->where('choice_order', $selectedChoiceOrder)->exists(), 422, __('quiz.answer_invalid'));
            }

            if ($attempt === null) {
                $now = now();
                $attemptIdx = (int) DB::table('quiz_attempts')->insertGetId([
                    'quiz_idx' => $idx,
                    'member_idx' => $participantIdx,
                    'actor_member_idx' => (int) ($actor?->idx ?? $member->idx),
                    'attempt_date' => $today,
                    'status' => 'in_progress',
                    'question_count' => (int) $lockedQuiz->question_count,
                    'quiz_version' => $questionVersion,
                    'is_test_only' => (int) $lockedQuiz->member_idx === (int) $member->idx ? 1 : 0,
                    'started_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $attempt = DB::table('quiz_attempts')->where('idx', $attemptIdx)->lockForUpdate()->first();
            }
            if ($reservation !== null) $randomActivities->attachResult($reservation, 'quiz_attempt', (int) $attempt->idx);

            DB::table('quiz_attempt_answers')->where('attempt_idx', $attempt->idx)->delete();
            $submittedAt = now();
            foreach ($answerSelections as $questionOrder => $selectedChoiceOrder) {
                $question = $questionsByOrder->get((int) $questionOrder);
                DB::table('quiz_attempt_answers')->insert([
                    'attempt_idx' => (int) $attempt->idx,
                    'quiz_idx' => $idx,
                    'question_idx' => (int) $question->idx,
                    'question_order' => (int) $questionOrder,
                    'selected_choice_order' => $selectedChoiceOrder,
                    'is_correct' => null,
                    'submitted_at' => $submittedAt,
                ]);
            }

            $answers = DB::table('quiz_attempt_answers as answer')
                ->join('quiz_questions as question', 'question.idx', '=', 'answer.question_idx')
                ->where('answer.attempt_idx', $attempt->idx)
                ->orderBy('answer.question_order')
                ->get(['answer.question_order', 'answer.selected_choice_order', 'question.correct_choice_order']);
            abort_unless($answers->count() === (int) $attempt->question_count, 409, __('quiz.attempt_unavailable'));
            $score = 0;
            foreach ($answers as $savedAnswer) {
                $isCorrect = (int) $savedAnswer->selected_choice_order === (int) $savedAnswer->correct_choice_order;
                if ($isCorrect) $score++;
                DB::table('quiz_attempt_answers')->where('attempt_idx', $attempt->idx)
                    ->where('question_order', $savedAnswer->question_order)->update(['is_correct' => $isCorrect]);
            }

            $isPerfect = $score === (int) $attempt->question_count;
            $isTestOnly = (bool) $attempt->is_test_only || (int) $lockedQuiz->member_idx === $participantIdx;
            $actorMemberIdx = (int) ($actor?->idx ?? $member->idx);
            $rewardReferences = ['actor_member_idx' => $actorMemberIdx];
            if ($reservation !== null) $rewardReferences['random_dummy_request_idx'] = (int) $reservation->idx;
            if (! $isTestOnly) {
                $creatorAction = 'quiz_creator_reward_'.(int) $attempt->question_count;
                $rewards->award((int) $lockedQuiz->member_idx, $creatorAction,
                    'quiz_creator:'.$idx.':'.$participantIdx, $rewardReferences + [
                        'memo' => 'First completion of quiz '.$idx.' by member '.$member->idx,
                    ]);
            }
            $completedAt = now('UTC');
            DB::table('quiz_attempts')->where('idx', $attempt->idx)->update([
                'status' => 'completed',
                'score' => $score,
                'is_perfect' => $isPerfect,
                'completed_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
            if ($reservation !== null) {
                $randomActivities->complete($reservation, 'quiz_attempt', (int) $attempt->idx);
                $randomActivities->log($request, $reservation, 'quiz_participated', '관리자 Random Dummy Mode로 더미 회원이 퀴즈에 참여했습니다.');
            }
            $awardedHearts = $isPerfect && ! $isTestOnly ? $rewards->award($participantIdx, 'quiz_perfect', 'quiz_perfect:'.$attempt->idx, $rewardReferences + [
                'memo' => 'Perfect score on quiz '.$idx,
            ]) : 0;
            DB::table('quiz_attempts')->where('idx', $attempt->idx)->update([
                'awarded_hearts' => $awardedHearts,
            ]);
        }, 3);

        if ($ownerRetryAllowed) session()->forget('quiz_retry_tokens.'.$idx);

        return redirect()->route('quizzes.show', $randomMode ? ['idx' => $idx, 'request_key' => $requestKey] : ['idx' => $idx]);
    }

    private function startRandomQuizAttempt(
        Request $request,
        int $quizIdx,
        object $quiz,
        object $actor,
        object $reservation,
        RandomDummyActivity $randomActivities,
    ): void {
        $version = (int) $request->input('quiz_version');
        DB::transaction(function () use ($request, $quizIdx, $quiz, $actor, $reservation, $randomActivities, $version): void {
            $lockedReservation = $randomActivities->lockReservation($reservation);
            if ($lockedReservation->replay) return;

            $lockedQuiz = DB::table('quizzes')->where('idx', $quizIdx)->whereNull('deleted_at')->lockForUpdate()->first();
            abort_if($lockedQuiz === null, 404);
            abort_unless($version === (int) $lockedQuiz->quiz_version, 409, __('quiz.edit_conflict'));
            $member = DB::table('members')->where('idx', $lockedReservation->member_idx)
                ->where('member_level', 1)->where('is_dummy', 1)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx']);
            abort_if($member === null, 422, __('quiz.random_dummy_no_eligible'));

            $today = now(config('app.timezone'))->toDateString();
            $attempt = DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', $member->idx)
                ->where('attempt_date', $today)->orderByDesc('idx')->lockForUpdate()->first();
            if ($attempt !== null && $attempt->status === 'completed'
                && (int) $lockedQuiz->member_idx !== (int) $member->idx) {
                throw ValidationException::withMessages(['random_dummy' => __('quiz.random_dummy_no_eligible')]);
            }
            // Keep the reserved dummy identity stable, but do not create an attempt until final submission.
        }, 3);
    }

    private function eligibleQuizParticipantDummy(int $quizIdx, int $creatorIdx): ?object
    {
        $today = now(config('app.timezone'))->toDateString();

        return DB::table('members as dummy')->where('dummy.member_level', 1)->where('dummy.is_dummy', 1)
            ->where('dummy.status', 'active')->whereNull('dummy.deleted_at')
            ->where(function ($eligible) use ($quizIdx, $creatorIdx, $today): void {
                $eligible->where('dummy.idx', $creatorIdx)
                    ->orWhereNotExists(function ($attempt) use ($quizIdx, $today): void {
                        $attempt->selectRaw('1')->from('quiz_attempts as existing_attempt')
                            ->whereColumn('existing_attempt.member_idx', 'dummy.idx')
                            ->where('existing_attempt.quiz_idx', $quizIdx)
                            ->where('existing_attempt.attempt_date', $today)
                            ->where('existing_attempt.status', 'completed');
                    });
            })->inRandomOrder()->lockForUpdate()->first(['dummy.idx']);
    }

    private function creatorRewardSettings(): array
    {
        $settings = DB::table('heart_reward_settings')
            ->whereIn('action_code', ['quiz_creator_reward_1', 'quiz_creator_reward_2', 'quiz_creator_reward_3'])
            ->get(['action_code', 'heart_amount', 'is_enabled'])->keyBy('action_code');
        $rewards = [];
        foreach ([1, 2, 3] as $questionCount) {
            $actionCode = 'quiz_creator_reward_'.$questionCount;
            $setting = $settings->get($actionCode);
            $rewards[$questionCount] = [
                'amount' => (int) ($setting?->heart_amount ?? HeartRewardManager::ACTIONS[$actionCode]['amount']),
                'enabled' => (bool) ($setting?->is_enabled ?? false),
            ];
        }

        return $rewards;
    }

    private function quizCreationQuota(int $memberIdx): array
    {
        $used = $this->todayQuizCreationCount($memberIdx);

        return [
            'limit' => self::DAILY_CREATION_LIMIT,
            'remaining' => max(0, self::DAILY_CREATION_LIMIT - $used),
        ];
    }

    private function todayQuizCreationCount(int $memberIdx): int
    {
        // Stored timestamps are UTC; quota days are bounded by Asia/Seoul midnight.
        $startUtc = now('Asia/Seoul')->startOfDay()->utc();
        $endUtc = $startUtc->copy()->addDay();

        return (int) DB::table('quiz_creation_histories')->where('member_idx', $memberIdx)
            ->where('created_at', '>=', $startUtc)->where('created_at', '<', $endUtc)->count();
    }

    private function eligibleQuizCreatorDummy(): ?object
    {
        $startUtc = now('Asia/Seoul')->startOfDay()->utc();
        $endUtc = $startUtc->copy()->addDay();

        return DB::table('members as dummy')->where('dummy.member_level', 1)->where('dummy.is_dummy', 1)
            ->where('dummy.status', 'active')->whereNull('dummy.deleted_at')
            ->whereRaw('(SELECT COUNT(*) FROM quiz_creation_histories AS history WHERE history.member_idx = dummy.idx AND history.created_at >= ? AND history.created_at < ?) < ?', [
                $startUtc->format('Y-m-d H:i:s.u'), $endUtc->format('Y-m-d H:i:s.u'), self::DAILY_CREATION_LIMIT,
            ])->inRandomOrder()->lockForUpdate()->first(['dummy.idx']);
    }

    private function quizSubmissionPayload(Request $request, array $validated, array $questions): array
    {
        $imageHash = static fn ($file): ?string => $file && is_string($file->getRealPath())
            ? hash_file('sha256', $file->getRealPath()) : null;
        $questionPayload = [];
        foreach ($questions as $index => $question) {
            $questionPayload[] = [
                'type' => $question['type'],
                'prompt' => $question['prompt'],
                'choices' => $question['choices'],
                'correct_choice_order' => $question['correct_choice_order'],
                'image_hash' => $imageHash($request->file('questions.'.$index.'.image')),
            ];
        }

        return [
            'title' => trim($validated['title']),
            'idol_idx' => (int) $validated['idol_idx'],
            'cover_image_hash' => $imageHash($request->file('cover_image')),
            'questions' => $questionPayload,
        ];
    }

    private function quizCreator(Request $request, bool $required = true): ?object
    {
        $member = $request->attributes->get('currentMember');
        $canCreate = $member !== null
            && in_array((int) $member->member_level, [1, 10], true)
            && (int) $member->is_dummy === 0;
        abort_if($required && ! $canCreate, 403);

        return $canCreate ? $member : null;
    }

    private function quizDummyMode(Request $request): bool
    {
        $actor = $request->attributes->get('currentMember');
        return (int) ($actor?->member_level ?? 0) === 10 && (int) ($actor?->is_dummy ?? 1) === 0
            && (app(RandomDummyActivity::class)->enabled($request)
                || (bool) $request->attributes->get('activityDummyMode', false));
    }

    private function authorizeQuizEdit(object $member, object $quiz): void
    {
        abort_unless((int) $quiz->member_idx === (int) $member->idx || (int) $member->member_level === 10, 403);
    }

    private function idolCategoryFor(?int $idolIdx): string
    {
        if ($idolIdx === null) return '';
        $idol = DB::table('idols')->where('idx', $idolIdx)->first(['type', 'gender']);
        if ($idol === null) return '';

        return match ([$idol->type, $idol->gender]) {
            ['individual', 'male'] => 'boy',
            ['group', 'male'] => 'boy_group',
            ['individual', 'female'] => 'girl',
            ['group', 'female'] => 'girl_group',
            default => '',
        };
    }

    private function insertQuestionVersion(int $quizIdx, int $version, array $questions, mixed $now): void
    {
        foreach ($questions as $order => $question) {
            $questionIdx = (int) DB::table('quiz_questions')->insertGetId([
                'quiz_idx' => $quizIdx,
                'quiz_version' => $version,
                'question_order' => $order + 1,
                'question_type' => $question['type'],
                'prompt' => $question['prompt'],
                'image_path' => $question['image_path'],
                'correct_choice_order' => $question['correct_choice_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($question['choices'] as $choiceOrder => $choiceText) {
                DB::table('quiz_question_choices')->insert([
                    'question_idx' => $questionIdx,
                    'choice_order' => $choiceOrder + 1,
                    'choice_text' => $choiceText,
                ]);
            }
        }
    }

    private function validatedQuestions(Request $request, ?int $quizIdx = null, ?int $quizVersion = null): array
    {
        $validator = Validator::make($request->all(), [
            'questions' => ['required', 'array', 'min:1', 'max:3'],
            'questions.*.type' => ['required', Rule::in(['image', 'choice', 'ox'])],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'questions.*.existing_question_idx' => ['nullable', 'integer', 'min:1'],
            'questions.*.choices' => ['sometimes', 'array', 'size:5'],
            'questions.*.choices.*' => ['required', 'string', 'max:200'],
            'questions.*.correct_choice_order' => ['nullable', 'integer', 'between:1,5'],
            'questions.*.correct_answer' => ['nullable', Rule::in(['O', 'X'])],
        ]);
        $validator->after(function ($validator) use ($request, $quizIdx, $quizVersion): void {
            $questions = $request->input('questions', []);
            if (! is_array($questions)) return;
            foreach (array_values($questions) as $index => $question) {
                if (! is_array($question)) continue;
                $type = $question['type'] ?? null;
                if (in_array($type, ['image', 'choice'], true)) {
                    if ($type === 'image' && ! $request->hasFile("questions.$index.image")) {
                        $existingQuestionIdx = $question['existing_question_idx'] ?? null;
                        $existingImage = $quizIdx !== null && is_scalar($existingQuestionIdx) && ctype_digit((string) $existingQuestionIdx)
                            ? DB::table('quiz_questions')->where('idx', (int) $existingQuestionIdx)->where('quiz_idx', $quizIdx)
                                ->where('quiz_version', $quizVersion)->where('question_type', 'image')->whereNotNull('image_path')->exists()
                            : false;
                        if (! $existingImage) $validator->errors()->add("questions.$index.image", __('quiz.image_required'));
                    }
                    $choices = $question['choices'] ?? null;
                    if (! is_array($choices) || count($choices) !== 5) {
                        $validator->errors()->add("questions.$index.choices", __('quiz.five_choices_required'));
                    }
                    if (! isset($question['correct_choice_order']) || ! in_array((int) $question['correct_choice_order'], [1, 2, 3, 4, 5], true)) {
                        $validator->errors()->add("questions.$index.correct_choice_order", __('quiz.correct_answer_required'));
                    }
                } elseif ($type === 'ox' && ! in_array($question['correct_answer'] ?? null, ['O', 'X'], true)) {
                    $validator->errors()->add("questions.$index.correct_answer", __('quiz.correct_answer_required'));
                }
            }
        });
        if ($validator->fails()) {
            throw ValidationException::withMessages(['quiz' => __('quiz.validation_failed')]);
        }

        $validated = $validator->validated();
        $result = [];
        foreach (array_values($validated['questions']) as $question) {
            $type = $question['type'];
            $choices = $type === 'ox' ? ['O', 'X'] : array_map('trim', $question['choices']);
            if (count(array_filter($choices, fn (string $choice): bool => $choice !== '')) !== 5 && $type !== 'ox') {
                throw ValidationException::withMessages(['quiz' => __('quiz.five_choices_required')]);
            }
            $correctOrder = $type === 'ox'
                ? ($question['correct_answer'] === 'O' ? 1 : 2)
                : (int) $question['correct_choice_order'];
            $result[] = [
                'type' => $type,
                'prompt' => trim($question['prompt']),
                'choices' => $choices,
                'correct_choice_order' => $correctOrder,
                'image_path' => null,
                'existing_question_idx' => isset($question['existing_question_idx']) ? (int) $question['existing_question_idx'] : null,
            ];
            $resultIndex = count($result) - 1;
            if ($type === 'image' && ! $request->hasFile("questions.$resultIndex.image") && $quizIdx !== null) {
                $result[array_key_last($result)]['image_path'] = DB::table('quiz_questions')
                    ->where('idx', $result[array_key_last($result)]['existing_question_idx'])
                    ->where('quiz_idx', $quizIdx)->where('quiz_version', $quizVersion)->value('image_path');
            }
        }

        return $result;
    }

    private function completedResult(int $attemptIdx)
    {
        return DB::table('quiz_attempt_answers as answer')
            ->join('quiz_questions as question', 'question.idx', '=', 'answer.question_idx')
            ->join('quiz_question_choices as selected', function ($join) {
                $join->on('selected.question_idx', '=', 'answer.question_idx')
                    ->on('selected.choice_order', '=', 'answer.selected_choice_order');
            })
            ->join('quiz_question_choices as correct', function ($join) {
                $join->on('correct.question_idx', '=', 'question.idx')
                    ->on('correct.choice_order', '=', 'question.correct_choice_order');
            })
            ->where('answer.attempt_idx', $attemptIdx)->orderBy('answer.question_order')
            ->get([
                'answer.question_order', 'answer.is_correct', 'question.prompt', 'question.question_type',
                'selected.choice_text as selected_text', 'correct.choice_text as correct_text',
            ]);
    }

    private function deleteUploadedImages(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, 'storage/')) {
                Storage::disk('public')->delete(substr($path, strlen('storage/')));
            }
        }
    }
}
