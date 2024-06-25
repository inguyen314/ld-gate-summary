<?php
//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
function set_options($db) {
	$stmnt_query = null;
	
    try {
		$sql = "alter session set  NLS_DATE_FORMAT='mm-dd-yyyy hh24:mi'";
        $stmnt_query = oci_parse($db, $sql);
        $status = oci_execute($stmnt_query);
        if ( !$status ) {
            $e = oci_error($db);
            trigger_error(htmlentities($e['message']), E_USER_ERROR);
        }
    }
    catch (Exception $e) {
        $status = "ERROR: Could set database session options";
    }
	finally {
		oci_free_statement($stmnt_query); 
	}
}
//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
function set_options2($db) {
	//change format to = yyyy-mm-dd hh24:mi
	$stmnt_query = null;
	
    try {
        // mm-dd-yyyy hh24:mi
		$sql = "alter session set  NLS_DATE_FORMAT='yyyy-mm-dd hh24:mi'";
        $stmnt_query = oci_parse($db, $sql);
        $status = oci_execute($stmnt_query);
        if ( !$status ) {
            $e = oci_error($db);
            trigger_error(htmlentities($e['message']), E_USER_ERROR);
            // throw new \RuntimeException(self::$status);
        }
    }
    catch (Exception $e) {
        $status = "ERROR: Could set database session options";
        // throw new \RuntimeException(self::$status);
    }
	finally {
		oci_free_statement($stmnt_query); 
	}
}
//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
function find_ld_gate_cwms_ts_id($db) {
	$stmnt_query = null;
	$data = [];
	
	try {		
		$sql = "with ld24 as (            
				select  'LD 24' as project_id,
                        'LD 24 Pool-Mississippi.Stage.Inst.30Minutes.0.29' as pool,
						'LD 24 TW-Mississippi.Stage.Inst.30Minutes.0.lrgsShef-rev' as tw,
						'Louisiana-Mississippi.Stage.Inst.15Minutes.0.lrgsShef-rev' as hinge,
						'LD 24 Pool-Mississippi.Opening.Inst.~2Hours.0.lpmsShef-raw-Taint' as taint,
						'' as roll
				from dual
				fetch first 1 rows only
				),

				ld25 as (            
				select  'LD 25' as project_id,
                        'LD 25 Pool-Mississippi.Stage.Inst.30Minutes.0.29' as pool,
						'LD 25 TW-Mississippi.Stage.Inst.30Minutes.0.lrgsShef-rev' as tw,
						'Mosier Ldg-Mississippi.Stage.Inst.30Minutes.0.lrgsShef-rev' as hinge,
						'LD 25 Pool-Mississippi.Opening.Inst.~2Hours.0.lpmsShef-raw-Taint' as taint,
						'LD 25 Pool-Mississippi.Opening.Inst.~2Hours.0.lpmsShef-raw-Roll' as roll
				from dual
				fetch first 1 rows only
				),

				mel_price as (            
				select  'Mel Price' as project_id,
                        'Mel Price Pool-Mississippi.Stage.Inst.15Minutes.0.29' as pool,
						'Mel Price TW-Mississippi.Stage.Inst.30Minutes.0.lrgsShef-rev' as tw,
						'Grafton-Mississippi.Stage.Inst.30Minutes.0.lrgsShef-rev' as hinge,
						'Mel Price Pool-Mississippi.Opening.Inst.~2Hours.0.lpmsShef-raw-Taint' as taint,
						'' as roll
				from dual
				fetch first 1 rows only
				),

				nav_kaskaskia as (            
				select  'Nav Pool' as project_id,
                        'Nav Pool-Kaskaskia.Stage.Inst.30Minutes.0.29' as pool,
						'Nav TW-Kaskaskia.Stage.Inst.30Minutes.0.lrgsShef-rev' as tw,
						'Red Bud-Kaskaskia.Stage.Inst.30Minutes.0.lrgsShef-rev' as hinge,
						'Nav Pool-Kaskaskia.Opening.Inst.~2Hours.0.lpmsShef-raw-Taint' as taint,
						'' as roll
				from dual
				fetch first 1 rows only
				)
				select ld24.project_id, ld24.pool, ld24.tw, ld24.hinge, ld24.taint, ld24.roll
				from ld24 ld24
				union all 
				select ld25.project_id, ld25.pool, ld25.tw, ld25.hinge, ld25.taint, ld25.roll
				from ld25 ld25
				union all 
				select mel_price.project_id, mel_price.pool, mel_price.tw, mel_price.hinge, mel_price.taint, mel_price.roll
				from mel_price mel_price
				union all 
				select nav_kaskaskia.project_id, nav_kaskaskia.pool, nav_kaskaskia.tw, nav_kaskaskia.hinge, nav_kaskaskia.taint, nav_kaskaskia.roll
				from nav_kaskaskia nav_kaskaskia";
		
		$stmnt_query = oci_parse($db, $sql);
		$status = oci_execute($stmnt_query);

		while (($row = oci_fetch_array($stmnt_query, OCI_ASSOC+OCI_RETURN_NULLS)) !== false) {
			$obj = (object) [
				"project_id" => $row['PROJECT_ID'],
				"pool" => $row['POOL'],
				"tw" => $row['TW'],
				"hinge" => $row['HINGE'],
				"taint" => $row['TAINT'],
				"roll" => $row['ROLL']
			];
			array_push($data, $obj);
		}
	}
	catch (Exception $e) {
		$e = oci_error($db);  
		trigger_error(htmlentities($e['message']), E_USER_ERROR);

		return null;
	}
	finally {
		oci_free_statement($stmnt_query); 
	}
	return $data;
}
//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
function find_ld_gate($db, $pool, $tw, $hinge, $taint, $roll) {
	$stmnt_query = null;
	$data = [];
	
	try {		
		$sql = "with cte_pool as (
				select cwms_ts_id
					, cwms_util.change_timezone(tsv.date_time, 'UTC', 'CST6CDT') as date_time
					, cwms_util.split_text('".$pool."', 1, '.') as location_id
					, cwms_util.split_text('".$pool."', 2, '.') as parameter_id
					, value
					, unit_id
					, quality_code
				from cwms_v_tsv_dqu tsv
				where 
					tsv.cwms_ts_id = '".$pool."'  
					and date_time >= cast(cast(current_date as timestamp) at time zone 'UTC' as date) - interval '24' hour
					and date_time <= cast(cast(current_date as timestamp) at time zone 'UTC' as date) + interval '0' day
					and (tsv.unit_id = 'ppm' or tsv.unit_id = 'F' or tsv.unit_id = 
						case 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Stage', 'Elev','Opening') then 'ft' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Precip', 'Depth') then 'in' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Conc-DO') then 'ppm'
						end or tsv.unit_id in ('cfs', 'umho/cm', 'volt'))
					and tsv.office_id = 'MVS' 
					and tsv.aliased_item is null
					-- Exclude rows where the minutes part of date_time is not 0 (i.e., 30-minute intervals)
					and to_number(to_char(tsv.date_time, 'MI')) = 0
				),
				tw as (
				select cwms_ts_id
					, cwms_util.change_timezone(tsv.date_time, 'UTC', 'CST6CDT') as date_time
					, cwms_util.split_text('".$tw."', 1, '.') as location_id
					, cwms_util.split_text('".$tw."', 2, '.') as parameter_id
					, value
					, unit_id
					, quality_code
				from cwms_v_tsv_dqu tsv
				where 
					tsv.cwms_ts_id = '".$tw."'  
					and date_time >= cast(cast(current_date as timestamp) at time zone 'UTC' as date) - interval '24' hour
					and date_time <= cast(cast(current_date as timestamp) at time zone 'UTC' as date) + interval '0' day
					and (tsv.unit_id = 'ppm' or tsv.unit_id = 'F' or tsv.unit_id = 
						case 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Stage', 'Elev','Opening') then 'ft' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Precip', 'Depth') then 'in' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Conc-DO') then 'ppm'
						end or tsv.unit_id in ('cfs', 'umho/cm', 'volt'))
					and tsv.office_id = 'MVS' 
					and tsv.aliased_item is null
					-- Exclude rows where the minutes part of date_time is not 0 (i.e., 30-minute intervals)
					and to_number(to_char(tsv.date_time, 'MI')) = 0
				),
				hinge as (
				select cwms_ts_id
					, cwms_util.change_timezone(tsv.date_time, 'UTC', 'CST6CDT') as date_time
					, cwms_util.split_text('".$hinge."', 1, '.') as location_id
					, cwms_util.split_text('".$hinge."', 2, '.') as parameter_id
					, value
					, unit_id
					, quality_code
				from cwms_v_tsv_dqu tsv
				where 
					tsv.cwms_ts_id = '".$hinge."'  
					and date_time >= cast(cast(current_date as timestamp) at time zone 'UTC' as date) - interval '24' hour
					and date_time <= cast(cast(current_date as timestamp) at time zone 'UTC' as date) + interval '0' day
					and (tsv.unit_id = 'ppm' or tsv.unit_id = 'F' or tsv.unit_id = 
						case 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Stage', 'Elev','Opening') then 'ft' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Precip', 'Depth') then 'in' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Conc-DO') then 'ppm'
						end or tsv.unit_id in ('cfs', 'umho/cm', 'volt'))
					and tsv.office_id = 'MVS' 
					and tsv.aliased_item is null
					-- Exclude rows where the minutes part of date_time is not 0 (i.e., 30-minute intervals)
					and to_number(to_char(tsv.date_time, 'MI')) = 0
				),
				taint as (
				select cwms_ts_id
					, cwms_util.change_timezone(tsv.date_time, 'UTC', 'CST6CDT') as date_time
					, cwms_util.split_text('".$taint."', 1, '.') as location_id
					, cwms_util.split_text('".$taint."', 2, '.') as parameter_id
					, value
					, unit_id
					, quality_code
				from cwms_v_tsv_dqu tsv
				where 
					tsv.cwms_ts_id = '".$taint."'  
					and date_time >= cast(cast(current_date as timestamp) at time zone 'UTC' as date) - interval '24' hour
					and date_time <= cast(cast(current_date as timestamp) at time zone 'UTC' as date) + interval '0' day
					and (tsv.unit_id = 'ppm' or tsv.unit_id = 'F' or tsv.unit_id = 
						case 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Stage', 'Elev','Opening') then 'ft' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Precip', 'Depth') then 'in' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Conc-DO') then 'ppm'
						end or tsv.unit_id in ('cfs', 'umho/cm', 'volt'))
					and tsv.office_id = 'MVS' 
					and tsv.aliased_item is null
					-- Exclude rows where the minutes part of date_time is not 0 (i.e., 30-minute intervals)
					and to_number(to_char(tsv.date_time, 'MI')) = 0
				),
				roll as (
				select cwms_ts_id
					, cwms_util.change_timezone(tsv.date_time, 'UTC', 'CST6CDT') as date_time
					, cwms_util.split_text('".$roll."', 1, '.') as location_id
					, cwms_util.split_text('".$roll."', 2, '.') as parameter_id
					, value
					, unit_id
					, quality_code
				from cwms_v_tsv_dqu tsv
				where 
					tsv.cwms_ts_id = '".$roll."'  
					and date_time >= cast(cast(current_date as timestamp) at time zone 'UTC' as date) - interval '24' hour
					and date_time <= cast(cast(current_date as timestamp) at time zone 'UTC' as date) + interval '0' day
					and (tsv.unit_id = 'ppm' or tsv.unit_id = 'F' or tsv.unit_id = 
						case 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Stage', 'Elev','Opening') then 'ft' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Precip', 'Depth') then 'in' 
							when cwms_util.split_text(tsv.cwms_ts_id, 2, '.') in ('Conc-DO') then 'ppm'
						end or tsv.unit_id in ('cfs', 'umho/cm', 'volt'))
					and tsv.office_id = 'MVS' 
					and tsv.aliased_item is null
					-- Exclude rows where the minutes part of date_time is not 0 (i.e., 30-minute intervals)
					and to_number(to_char(tsv.date_time, 'MI')) = 0
				)
				select  pool.date_time, pool.cwms_ts_id as pool_cwms_ts_id, pool.value as pool, pool.location_id as pool_location_id,
						tw.cwms_ts_id as tw_cwms_ts_id, tw.value as tw, tw.location_id as tw_location_id,
						hinge.cwms_ts_id as hinge_cwms_ts_id, hinge.value as hinge, hinge.location_id as hinge_location_id,
						taint.cwms_ts_id as taint_cwms_ts_id, taint.value as taint, taint.location_id as taint_location_id,
						roll.cwms_ts_id as roll_cwms_ts_id, roll.value as roll, roll.location_id as roll_location_id
						--pool.cwms_ts_id, pool.date_time, pool.location_id, pool.parameter_id, pool.value, pool.unit_id, pool.quality_code,
						--tw.cwms_ts_id, tw.date_time, tw.location_id, tw.parameter_id, tw.value, tw.unit_id, tw.quality_code,
						--hinge.cwms_ts_id, hinge.date_time, hinge.location_id, hinge.parameter_id, hinge.value, hinge.unit_id, hinge.quality_code,
						--taint.cwms_ts_id, taint.date_time, taint.location_id, taint.parameter_id, taint.value, taint.unit_id, taint.quality_code,
						--roll.cwms_ts_id, roll.date_time, roll.location_id, roll.parameter_id, roll.value, roll.unit_id, roll.quality_code
				from  cte_pool pool
					left join tw tw on
					pool.date_time=tw.date_time
						left join hinge hinge on
						pool.date_time=hinge.date_time
							left join taint taint on
							pool.date_time=taint.date_time
								left join roll roll on
								pool.date_time=roll.date_time
				order by pool.date_time desc";
		
		$stmnt_query = oci_parse($db, $sql);
		$status = oci_execute($stmnt_query);

		while (($row = oci_fetch_array($stmnt_query, OCI_ASSOC+OCI_RETURN_NULLS)) !== false) {	
			$obj = (object) [
				"date_time" => $row['DATE_TIME'],
				"pool_cwms_ts_id" => $row['POOL_CWMS_TS_ID'],
				"pool" => $row['POOL'],
				"pool_location_id" => $row['POOL_LOCATION_ID'],
				"tw_cwms_ts_id" => $row['TW_CWMS_TS_ID'],
				"tw" => $row['TW'],
				"tw_location_id" => $row['TW_LOCATION_ID'],
				"hinge_cwms_ts_id" => $row['HINGE_CWMS_TS_ID'],
				"hinge" => $row['HINGE'],
				"hinge_location_id" => $row['HINGE_LOCATION_ID'],
				"taint_cwms_ts_id" => $row['TAINT_CWMS_TS_ID'],
				"taint" => $row['TAINT'],
				"taint_location_id" => $row['TAINT_LOCATION_ID'],
				"roll_cwms_ts_id" => $row['ROLL_CWMS_TS_ID'],
				"roll" => $row['ROLL'],
				"roll_location_id" => $row['ROLL_LOCATION_ID'],
			];
			array_push($data, $obj);
		}
	}
	catch (Exception $e) {
		$e = oci_error($db);  
		trigger_error(htmlentities($e['message']), E_USER_ERROR);

		return null;
	}
	finally {
		oci_free_statement($stmnt_query); 
	}
	return $data;
}
//------------------------------------------------------------------------------------------------------------------
//------------------------------------------------------------------------------------------------------------------
?>
