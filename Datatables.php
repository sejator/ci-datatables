<?php

/**
 * Datatables.php.
 *
 * Sejator <sejatordev@gmail.com>
 * Github : https://github.com/sejator
 * Edit : 2023-08-08
 * 
 * Fork dari: https://github.com/irsyadulibad/ci-datatable
 */

class Datatables
{
	private $db;
	private $input;
	private $output;
	private $table;
	private $alias = [];
	private $whereFields = [];
	private $whereData;
	private $joins = [];
	private $colum_search = [];

	public function __construct()
	{
		$ci = &get_instance();
		$this->db = $ci->db;
		$this->input = $ci->input;
		$this->output = $ci->output;
	}

	public function table($table)
	{
		$this->table = $table;
		return $this;
	}

	public function select($fields)
	{
		$this->db->select($fields);
		$this->set_alias($fields);
		return $this;
	}

	public function where($data)
	{
		$this->db->where($data);
		foreach ($data as $field => $value) {
			$this->whereFields[] = $field;
		}
		$this->whereData = $data;
		return $this;
	}

	public function join($table, $cond, $type = '')
	{
		$this->joins[] = ['table' => $table, 'cond' => $cond, 'type' => $type];
		$this->db->join($table, $cond, $type);
		return $this;
	}

	public function group_by($field)
	{
		$this->db->group_by($field);
		return $this;
	}

	public function order_by($field)
	{
		$this->db->order_by($field);
		return $this;
	}

	public function draw()
	{
		$keyword = $this->input->post_get('search')['value'];
		if ($keyword != '') $this->get_filtering($keyword);
		$this->get_ordering();
		$result = $this->get_result();
		$paging = $this->get_paging($keyword);

		$data = [
			'draw' => $this->input->post_get('draw'),
			'recordsTotal' => $paging['total'],
			'recordsFiltered' => $paging['filtered'],
			'data' => $result
		];

		// echo $this->db->last_query();
		// die;

		$this->output
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($data))
			->_display();
		exit;
	}

	private function set_alias($data)
	{
		foreach (explode(',', $data) as $val) {
			if (stripos($val, 'as')) {
				$alias = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$2', $val));
				$field = trim(preg_replace('/(.*)\s+as\s+(\w*)/i', '$1', $val));
				$this->alias[$alias] = $field;
				$this->colum_search[] = $field;
			} else {
				$this->colum_search[] = $val;
			}
		}
	}

	private function do_join()
	{
		foreach ($this->joins as $join) {
			$this->db->join($join['table'], $join['cond'], $join['type']);
		}
	}

	private function get_filtering($keyword)
	{
		$this->db->group_start();
		foreach ($this->colum_search as $key => $field) {
			($key < 1) ? $this->db->like($field, $keyword) : $this->db->or_like($field, $keyword);
		}
		$this->db->group_end();
	}

	private function get_ordering()
	{
		$orderField = $this->input->post_get('order')[0]['column'];
		$orderAD = $this->input->post_get('order')[0]['dir'];
		$orderColumn = $this->input->post_get('columns')[$orderField]['data'];
		$this->db->order_by($orderColumn, $orderAD);
	}

	private function get_result()
	{
		$this->get_limiting();
		return $this->db->get($this->table)->result_array();
	}

	private function get_limiting()
	{
		$limit = $this->input->post_get('length', true);
		$start = $this->input->post_get('start', true);
		$this->db->limit($limit, $start);
	}

	private function get_paging($keyword)
	{
		$total = $this->db->count_all_results($this->table);

		if (count($this->joins) > 0) $this->do_join();
		if (!is_null($this->whereData)) $this->where($this->whereData);
		if ($keyword != '') {
			$this->get_filtering($keyword);
			$total = $this->db->count_all_results($this->table);
		}

		if (count($this->whereData) > 0) {
			$filtered = $this->db->where($this->whereData)->get($this->table)->num_rows();
		} else {
			$filtered = $this->db->get($this->table)->num_rows();
		}

		if ($keyword == '') {
			return [
				'total' => $filtered,
				'filtered' => $filtered
			];
		} else {
			return [
				'total' => $filtered,
				'filtered' => $total
			];
		}
	}
}
