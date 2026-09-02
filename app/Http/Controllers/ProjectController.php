<?php
namespace App\Http\Controllers;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ProjectController extends Controller {
    public function index(){return view('projects.index',['projects'=>Project::orderByDesc('is_active')->orderBy('contract_end_date')->get()]);}
    public function store(Request $request){Project::create($this->validated($request));return back()->with('success','Project berhasil ditambahkan.');}
    public function update(Request $request,Project $project){$project->update($this->validated($request));return back()->with('success','Project berhasil diperbarui.');}
    public function destroy(Project $project){$project->delete();return back()->with('success','Project berhasil dihapus.');}
    private function validated(Request $request):array{return $request->validate(['name'=>['required','string','max:180'],'customer_name'=>['required','string','max:180'],'contract_start_date'=>['required','date'],'contract_end_date'=>['required','date','after_or_equal:contract_start_date'],'location'=>['required','string','max:255'],'maintenance_interval_months'=>['required',Rule::in([3,6,12])],'maintenance_anchor_date'=>['required','date'],'notes'=>['nullable','string','max:2000'],'is_active'=>['nullable','boolean']])+['is_active'=>$request->boolean('is_active')];}
}
