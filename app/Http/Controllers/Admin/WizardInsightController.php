<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Services\RecommendationEngine;
use App\Services\WizardInsights;
use App\Services\WizardWeights;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Smart Wizard & AI Insights (proposal hal. 51). */
class WizardInsightController extends Controller
{
    public function index(Request $request, RecommendationEngine $engine, WizardWeights $weights)
    {
        $insights = new WizardInsights($request->integer('days', 30));

        // Simulasi: jalankan engine dengan bobot aktif TANPA mencatat ke wizard_queries
        $sim = collect($request->query('sim', []))->only([...RecommendationEngine::CRITERIA, 'budget'])
            ->filter(fn ($v) => is_string($v) && $v !== '')->all();
        $simulation = $sim ? $engine->recommend($sim, 5) : null;

        return view('admin.wizard.index', [
            'days' => $insights->days(),
            'summary' => $insights->summary(),
            'colors' => $insights->share('color', 5),
            'combos' => $insights->popularCombos(4),
            'budgets' => $insights->budgetByOccasion(5),
            'gaps' => $insights->demandGaps(5),
            'highlights' => $insights->highlights(),
            'weights' => $weights->current(),
            'customWeights' => $weights->isCustom(),
            'options' => $engine->options(),
            'sim' => $sim,
            'simulation' => $simulation,
        ]);
    }

    public function updateWeights(Request $request, WizardWeights $weights)
    {
        $rules = [];
        foreach (WizardWeights::FACTORS as $key => [$label]) {
            $rules["weights.{$key}"] = ['required', 'integer', 'min:' . WizardWeights::MIN[$key], 'max:80'];
        }
        $data = $request->validate($rules, [], collect(WizardWeights::FACTORS)->mapWithKeys(fn ($f, $k) => ["weights.{$k}" => 'bobot ' . strtolower($f[0])])->all());

        $percent = array_map('intval', $data['weights']);
        if (array_sum($percent) !== 100) {
            throw ValidationException::withMessages(['weights' => 'Total bobot harus tepat 100% (sekarang ' . array_sum($percent) . '%).']);
        }

        $weights->save($percent);

        return redirect()->route('admin.wizard.index')->with('success', 'Bobot algoritma disimpan dan langsung dipakai Smart Wizard.');
    }

    public function resetWeights(WizardWeights $weights)
    {
        $weights->reset();

        return redirect()->route('admin.wizard.index')->with('success', 'Bobot dikembalikan ke pengaturan bawaan.');
    }
}
