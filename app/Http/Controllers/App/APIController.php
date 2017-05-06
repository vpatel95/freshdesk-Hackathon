<?php

namespace App\Http\Controllers\App;

use App\User;
use App\UserDetail;
use App\Hospital;
use App\Ambulance;
use App\PoliceStation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Events\HospitalNearBy;
use App\Events\PoliceComplaints;
use App\Events\AmbulanceRequested;
use App\Events\PoliceEmergencyAccident;
use App\Events\HospitalEmergencyPersonal;

class APIController extends Controller {
    
    private $token = 'UWv2zdE2dYRCTXjUOQFGmgEGPjsQzkiXLrGXLeotUWhw8QExKZbhXU28e3x3';

    public function __construct() {
        
        $this->middleware('guest');
    }

    private function getDistance($lat1, $lat2, $lon1, $lon2) {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $km = $dist * 60 * 1.1515 * 1.609344;
        return $dist;
    }

    private function sortHospital($lat, $lon) {

        $hos = Hospital::all();

        if(sizeof($hos) == 0){
            return response()->json([
                'hospital' => 'NO_HOSPITAL_FOUND'
            ]);
        }

        for ($i=0; $i < sizeof($hos); $i++) { 
            $h[$i]['id'] = $hos[$i]['id'];
            $h[$i]['dist'] = $this->getDistance($lat, $hos[$i]['latitude'],$lon, $hos[$i]['longitude']);
        }

        $hc = collect($h)->sortByDesc('dist');
        return $hc;
    }

    private function sortPoliceStation($lat, $lon) {

        $ps = PoliceStation::all();

        if(sizeof($ps) == 0) {
            return response()->json([
                'police_station' => 'NO_POLICE_FOUND'
            ]);
        }

        for ($i=0; $i < sizeof($ps); $i++) { 
            $p[$i]['id'] = $ps[$i]['id'];
            $p[$i]['dist'] = $this->getDistance($lat, $ps[$i]['latitude'], $lon, $ps[$i]['longitude']);
        }

        $pc = collect($p)->sortByDesc('dist');
        return $pc;
    }

    public function eventHEA(Request $request) {

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

    	$user = $request['user'];
        $lat = $request['lat'];
        $lon = $request['lon'];
        $self = $request['self'] === 'true' ? true : false;

        $contact = UserDetail::find($user)->phone_no;

        $pc = $this->sortPoliceStation($lat, $lon);
        $ps_id = $pc->last();
        
        $hc = $this->sortHospital($lat, $lon);
        for ($i=0; $hc != null; $i++) { 
            $ha = $hc->last();
            if(Ambulance::where('h_id', $ha['id'])->where('occupied',false)->exists()) {
                $ambulance = Ambulance::where('h_id', $ha['id'])->where('occupied',false)->first();                    
                if(event(new \App\Events\HospitalEmergencyAccident($user, $ha['id'], $ps_id['id'], $lat, $lon, $self))){
                    if(event(new AmbulanceRequested($user, $contact, $lat, $lon, $ha['id'], $ambulance->id))){
                        if(event(new PoliceEmergencyAccident($ps_id['id'], $user, $ha['id'], $lat, $lon))) {
                            $am = Ambulance::find($ambulance->id);
                            $am->occupied = true;
                            $am->save();

                            $hea = new \App\HospitalEmergencyAccident();
                            $hea->type = 'accident';
                            $hea->u_id = $user;
                            $hea->h_id = $ha['id'];
                            $hea->ps_id = $ps_id['id'];
                            $hea->latitude = $lat;
                            $hea->longitude = $lon;
                            $hea->address = 'NULL';
                            $hea->self = $self;
                            $hea->save();

                            return response()->json([
                                'SUCCESS' => 'EVENT_FIRED'
                            ]);
                        }
                    }
                }   
            } else {
                $hc->pop();
            }
        }

        return response()->json([
            'ERROR' => 'AMBULANCE_NOT_AVAILABLE'
        ]);
    }

    public function eventHEP(Request $request) {
        
        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $user = $request['user'];
        $h_id = $request['h_id'];
        $lat = $request['lat'];
        $lon = $request['lon'];
        $self = $request['self'];

        $contact = UserDetail::find($user)->phone_no;
        
        $hc = $this->sortHospital($lat, $lon);
        for ($i=0; $hc != null; $i++) { 
            $ha = $hc->last();
            if(Ambulance::where('h_id', $ha['id'])->where('occupied',false)->exists()) {
                $ambulance = Ambulance::where('h_id', $ha['id'])->where('occupied',false)->first();                    
                if(event(new \App\Events\HospitalEmergencyPersonal($user, $ha['id'], $lat, $lon, $self))){
                    if(event(new AmbulanceRequested($user, $contact, $lat, $lon, $ha['id'], $ambulance->id))){
                        $am = Ambulance::find($ambulance->id);
                        $am->occupied = true;
                        $am->save();

                        $hea = new \App\HospitalEmergencyAccident();
                        $hea->type = 'personal';
                        $hea->u_id = $user;
                        $hea->h_id = $ha['id'];
                        $hea->latitude = $lat;
                        $hea->longitude = $lon;
                        $hea->address = 'NULL';
                        $hea->self = $self;
                        $hea->save();

                        return response()->json([
                            'SUCCESS' => 'EVENT_FIRED'
                        ]);                    
                    }
                }   
            } else {
                $hc->pop();
            }
        }

        return response()->json([
            'ERROR' => 'AMBULANCE_NOT_AVAILABLE'
        ]);
    }

    public function eventHNB(Request $request) {

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $user = $request['user_id'];
        $h_id = $request['h_id'];
        $disease = $request['disease'];

        if(event(new HospitalNearBy($user, $h_id, $disease)))
            return response()->json([
                'SUCCESS' => 'EVENT_FIRED'
            ]); 

        return response()->json([
            'ERROR' => 'EVENT_FIRE_FAIL'
        ]);       
    }

    public function eventPF(Request $request){

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $user = $request['u_id'];
        $police = $request['ps_id'];
        $category = $request['category'];
        $description = $request['description'];
        $lat = $request['lat'];
        $lon = $request['lon'];

        if($category === 'Lost & Found') {
            $file = $request->file('media');
            $filename = $request['filename'].'.png';

            if($file) {
                Storage::disk('local')->put($filename, File::get($file));
            } 
        } else {
            $filename = 'NULL';
        }

        if(event(new PoliceComplaints($user, $police, $category, $description, $filename, $lat, $lon))) {
            return response()->json([
                'SUCCESS' => 'RECEIVED_FIR'
            ]);
        }

        return response()->json([
            'ERROR' => 'EVENT_FIRE_FAIL'
        ]); 
    }

    public function getHospitalBySpeciality(Request $request) {
        
        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $sp = $request['specialization'];
        $hs_sp = Hospital::where('specialization->sp',$sp)->get();

        if(sizeof($hs_sp) == 0){
            return response()->json([
                'hospital' => 'NO_HOSPITAL_FOUND'
            ]);
        }

        for ($i=0; $i < sizeof($hs_sp); $i++) { 
            $hos[$i]['id'] = $hs_sp[$i]['id'];
            $hos[$i]['name'] = $hs_sp[$i]['name'];
        }

        return response()->json([
            'hospital' => $hos
        ]);
    }

    public function getHospitalByRating(Request $request) {

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $rating = $request['rating'];

        $hospital = Hospital::where('rating', '>', $rating)->orderBy('rating', 'desc')->get();

        if(sizeof($hospital) == 0){
            return response()->json([
                'hospital' => 'NO_HOSPITAL_FOUND'
            ]);
        }

        for ($i=0; $i < sizeof($hospital); $i++) { 
            $hos[$i]['id'] = $hs_sp[$i]['id'];
            $hos[$i]['name'] = $hs_sp[$i]['name'];
        }

        return response()->json([
            'hospital' => $hospital
        ]);   
    }

    public function getHospitalByDistance(Request $request) {
        
        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $lat = $request['lat'];
        $lon = $request['lon'];

        $hos = Hospital::all();

        if(sizeof($hos) == 0){
            return response()->json([
                'hospital' => 'NO_HOSPITAL_FOUND'
            ]);
        }

        for ($i=0; $i < sizeof($hos); $i++) { 
            $h[$i]['id'] = $hos[$i]['id'];
            $h[$i]['name'] = $hos[$i]['name'];
            $h[$i]['lat'] = $hos[$i]['latitude'];
            $h[$i]['lon'] = $hos[$i]['longitute'];
            $h[$i]['dist'] = $this->getDistance($lat, $hos[$i]['latitude'],$lon, $hos[$i]['longitude']);
            $h[$i]['rating'] = $hos[$i]['rating'];
        }

        $hc = collect($h)->sortBy('dist');
        return response()->json([
            'hospital' => $hc
        ]);
    }

    public function getAmbulanceLocation(Request $request) {

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $lat = $request['lat'];
        $lon = $request['lon'];
        $ambulance = $request['ambulance'];

        if(event(new AmbulanceRequested(null, null, $lat, $lon, null, $ambulance)))
            return response()->json([
                'SUCCESS' => 'EVENT_FIRED'
            ]);
    }

    public function unoccupyAmbulance(Request $request) {

        if($this->token != $request['token']){
            return response()->json([
                'ERROR' => 'TOKEN_MISMATCH'
            ]);
        }

        $ambulance = $request['ambulance'];

        $am = Ambulance::find($ambulance);
        $am->occupied = false;
        $am->save();

        return response()->json([
            'SUCCESS' => 'AMBULANCE_FREED'
        ]);
    }
}
