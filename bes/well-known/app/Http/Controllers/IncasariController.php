<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Incasari;
use App\Models\Clienti;
use App\Models\Comenzi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class IncasariController extends Controller
{
    public function index()
    {
        // Get today's date using Laravel's now() helper
        $today = now()->format('d/m/Y');
        
        return view('incasari.index', [
            'title' => 'Incasari',
            'incasari_active' => 'active',
            'today' => $today, // Passing today's date to the view
        ]);
    }

    public function getData(Request $request)
    {
        // Get date from request
        $q_data = $request->input('dt');
        $q_location = $request->input('q_location');
        $q_method = $request->input('q_method');
		
        $page = $request->input('page', 1);
        $per_page = 50;
        $adjacents = 4;
        $offset = ($page - 1) * $per_page;

        // Convert date format
        $dat = Carbon::createFromFormat('d/m/Y', $q_data);
        $q_data_f = $dat->format('Y-m-d');

        // Count total records
        $numrowsQuery = DB::table('incasari')
            ->leftJoin('clienti', 'incasari.idclient', '=', 'clienti.idclienti')
            ->leftJoin('comenzi', 'comenzi.idcomanda', '=', 'incasari.idcmd')
            ->where('incasari.data', $q_data_f)
			->where(function($query) {
				$query->where('incasari.idcmd', 0)
					  ->orWhereNotNull('comenzi.idcomanda');
			});
			
			if (!empty($q_location)) {
				$numrowsQuery->where('incasari.locatie_mgz', $q_location);
			}

			if (!empty($q_method)) {
				$numrowsQuery->where('incasari.idstare', $q_method);
			}
			$numrows = $numrowsQuery->count();

        // Ensure pagination is at least 1 page even if no data exists
        $total_pages = ($numrows > 0) ? ceil($numrows / $per_page) : 1;

        // Get total for the day
        $tot_zi = DB::table('incasari')
            ->where('data', $q_data_f);
		if (!empty($q_location)) {
			$tot_zi->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_zi->where('idstare', $q_method);
		}
		$tot_zi_sum = $tot_zi->sum('suma');
        $tot_zi_f = number_format($tot_zi_sum, 0, ',', '.');
		

        // Get total cash
        $tot_cash = DB::table('incasari')
            ->where('data', $q_data_f)
            ->whereIn('idstare', [3, 11]);
		if (!empty($q_location)) {
			$tot_cash->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			if($q_method == 3){
				$tot_cash->whereIn('idstare', [3, 11]);
			}else{
				$tot_cash->where('idstare', $q_method);
			}
		}
		$tot_cash_sum = $tot_cash->sum('suma');
        $tot_cash_f = number_format($tot_cash_sum, 0, ',', '.');
		

        // Get total card
        $tot_card = DB::table('incasari')
            ->where('data', $q_data_f)
            ->whereIn('idstare', [6, 12]);
		if (!empty($q_location)) {
			$tot_card->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			if($q_method == 6){
				$tot_card->whereIn('idstare', [6, 12]);
			}else{
				$tot_card->where('idstare', $q_method);
			}
		}
		$tot_card_sum = $tot_card->sum('suma');
        $tot_card_f = number_format($tot_card_sum, 0, ',', '.');
		

        // Get total FD
        $tot_fd = DB::table('incasari')
            ->where('data', $q_data_f)
            ->whereIn('idstare', [7, 10]);
		if (!empty($q_location)) {
			$tot_fd->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			if($q_method == 7){
				$tot_fd->whereIn('idstare', [7, 10]);
			}else{
				$tot_fd->where('idstare', $q_method);
			}
		}
		$tot_fd_sum = $tot_fd->sum('suma');
        $tot_fd_f = number_format($tot_fd_sum, 0, ',', '.');
		

        // Get total Retur
        $tot_retur = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 5);
		if (!empty($q_location)) {
			$tot_retur->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_retur->where('idstare', $q_method);
		}
		$tot_retur_sum = $tot_retur->sum('suma');
        $tot_retur_f = number_format($tot_retur_sum, 0, ',', '.');
		

        // Get total Avans
        $tot_avans = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 4);
		if (!empty($q_location)) {
			$tot_avans->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_avans->where('idstare', $q_method);
		}
		$tot_avans_sum = $tot_avans->sum('suma');
        $tot_avans_f = number_format($tot_avans_sum, 0, ',', '.');
		
	
        // Get total OP
        $tot_op = DB::table('incasari')
            ->where('data', $q_data_f)
            ->whereIn('idstare', [9, 13]);
		if (!empty($q_location)) {
			$tot_op->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			if($q_method == 9){
				$tot_op->whereIn('idstare', [9, 13]);
			}else{
				$tot_op->where('idstare', $q_method);
			}
		}
		$tot_op_sum = $tot_op->sum('suma');
        $tot_op_f = number_format($tot_op_sum, 0, ',', '.');
		
		
		
        // Get total Avans FD
/*         $tot_avans_fd = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 10);
		if (!empty($q_location)) {
			$tot_avans_fd->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_avans_fd->where('idstare', $q_method);
		}
		$tot_avans_fd_sum = $tot_avans_fd->sum('suma');
        $tot_avans_fd_f = number_format($tot_avans_fd_sum, 0, ',', '.'); */
		
		
        // Get total Avans Cash
/*         $tot_avans_cash = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 11);
		if (!empty($q_location)) {
			$tot_avans_cash->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_avans_cash->where('idstare', $q_method);
		}
		$tot_avans_cash_sum = $tot_avans_cash->sum('suma');
        $tot_avans_cash_f = number_format($tot_avans_cash_sum, 0, ',', '.'); */
		
		
        // Get total Avans Card
/*         $tot_avans_card = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 12);
		if (!empty($q_location)) {
			$tot_avans_card->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_avans_card->where('idstare', $q_method);
		}
		$tot_avans_card_sum = $tot_avans_card->sum('suma');
        $tot_avans_card_f = number_format($tot_avans_card_sum, 0, ',', '.'); */
		
		
        // Get total Avans OP
/*         $tot_avans_op = DB::table('incasari')
            ->where('data', $q_data_f)
            ->where('idstare', 13);
		if (!empty($q_location)) {
			$tot_avans_op->where('locatie_mgz', $q_location);
		}
		if (!empty($q_method)) {
			$tot_avans_op->where('idstare', $q_method);
		}
		$tot_avans_op_sum = $tot_avans_op->sum('suma');
        $tot_avans_op_f = number_format($tot_avans_op_sum, 0, ',', '.'); */
		

        // Get data for the table
        $dataQuery = DB::table('incasari')
            ->select(
                'incasari.id',
                'incasari.idcmd',
                'incasari.idclient',
                'incasari.suma',
                'incasari.data',
                'incasari.data_time',
                'incasari.idstare',
                'incasari.cstmtext',
                'incasari.locatie_mgz',
                'clienti.nume',
                'clienti.companie',
                'comenzi.data as datacmd',
				'users.username as user_name'
            )
            ->leftJoin('users', 'incasari.userid', '=', 'users.Id')
            ->leftJoin('clienti', 'incasari.idclient', '=', 'clienti.idclienti')
            ->leftJoin('comenzi', 'comenzi.idcomanda', '=', 'incasari.idcmd')
            ->where('incasari.data', $q_data_f)
			->where(function($query) {
				$query->where('incasari.idcmd', 0)
					  ->orWhereNotNull('comenzi.idcomanda');
			});
			
			if (!empty($q_location)) {
				$dataQuery->where('incasari.locatie_mgz', $q_location);
			}

			if (!empty($q_method)) {
				$dataQuery->where('incasari.idstare', $q_method);
			}

            $data = $dataQuery->orderBy('incasari.id', 'desc')
            ->offset($offset)
            ->limit($per_page)
            ->get();
			
		$timestampStart = mktime(0, 0, 0);
		$timestampEnd = mktime(23, 59, 59);
		$amount = DB::table('incasari_entries')->whereBetween('date', [$timestampStart, $timestampEnd])->value('amount');
		if($amount){
			$tot_zi_f = $tot_zi_f+$amount;
		}
		
	
        return view('incasari.table', [
            'data' => $data,
            'numrows' => $numrows,
            'tot_zi_f' => $tot_zi_f,
            'tot_cash_f' => $tot_cash_f,
            'tot_card_f' => $tot_card_f,
            'tot_fd_f' => $tot_fd_f,
            'tot_retur_f' => $tot_retur_f,
            'tot_avans_f' => $tot_avans_f,
            'tot_op_f' => $tot_op_f,
            //'tot_avans_fd_f' => $tot_avans_fd_f,
            //'tot_avans_cash_f' => $tot_avans_cash_f,
           // 'tot_avans_card_f' => $tot_avans_card_f,
           // 'tot_avans_op_f' => $tot_avans_op_f,
            'page' => $page,
            'total_pages' => $total_pages,
            'adjacents' => $adjacents,
        ]);
    }
		
	public function getDailyPrice()
	{
		// Today's start and end timestamps
		$timestampStart = mktime(0, 0, 0);
		$timestampEnd = mktime(23, 59, 59);

		$amount = DB::table('incasari_entries')
					->whereBetween('date', [$timestampStart, $timestampEnd])
					->value('amount');

		return response()->json(['amount' => $amount ?? 0]);
	}

	public function updateDailyPrice(Request $request)
	{
		// Map location to integer
		$locationMap = [
			'TM' => 1,
			'UTVIN' => 2
		];
		
		$locationValue = $locationMap[$request->location] ?? 0;

		DB::table('incasari')->insert([
			'idcmd'       => 0,
			'userid'       => Auth::user()->Id,
			'idstare'     => 3,
			'suma'        => $request->amount,
			'data'        => now(), // current timestamp
			'data_time'        => now()->format('H:i:s'), // current timestamp
			'idclient'    => 0,
			'cstmtext'    => $request->text,
			'locatie_mgz' => $locationValue
		]);

		return response()->json(['success' => true]);
	}
}
