<?php
namespace App\Http\Controllers;
use App\Models\Device;
use App\Models\Project;
use App\Services\IndonesiaMapService;
use App\Services\SlaService;
class TopologyController extends Controller {
    public function project(Project $project, SlaService $sla, IndonesiaMapService $map){$project->load(['devices'=>fn($query)=>$query->orderBy('region')->orderBy('name')]);$sla->applyToDevices($project->devices);$projectSla=$sla->projectAverage($project->devices);$coordinates=$project->devices->filter(fn(Device $device)=>$device->latitude && $device->longitude && (float)$device->latitude !== 0.0 && (float)$device->longitude !== 0.0);$projectLatitude=$coordinates->isNotEmpty()?(float)$coordinates->avg('latitude'):null;$projectLongitude=$coordinates->isNotEmpty()?(float)$coordinates->avg('longitude'):null;[$mapLeft,$mapTop]=$map->percentage($project->name.' '.$project->location.' '.$project->devices->pluck('region')->filter()->implode(' ').' '.$project->devices->pluck('city')->filter()->implode(' '));return view('topology.project',compact('project','projectSla','projectLatitude','projectLongitude','mapLeft','mapTop'));}
}
