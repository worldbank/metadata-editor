<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * GIS helper function
 *
 * @package		CodeIgniter
 * @subpackage	Helpers
 * @category	Helpers
 */

// ------------------------------------------------------------------------


  /**
   * bbox_to_wkt
   *
   * Converts bounding box to WKT
   *
   * @access	public
   * @param	float
   * @param	float
   * @param	float
   * @param	float
   * @return	string
   */
  function bbox_to_wkt($north, $south, $east, $west)
  {
	if (!is_numeric($north) || !is_numeric($south) || !is_numeric($east) || !is_numeric($west) )
	{
	  return FALSE;
	}
	
	return "POLYGON(($west $north, $east $north, $east $south, $west $south, $west $north))";
  }

  /**
   * Wrap longitude to [-180, 180], keeping 180 as 180.
   *
   * @param mixed $lng
   * @return float|null
   */
  function wrap_longitude($lng)
  {
	if (!is_numeric($lng))
	{
		return NULL;
	}

	$lng = (float) $lng;
	if (is_nan($lng) || is_infinite($lng))
	{
		return NULL;
	}

	if ($lng === 180.0)
	{
		return 180.0;
	}

	$wrapped = fmod($lng + 180.0, 360.0);
	if ($wrapped < 0)
	{
		$wrapped += 360.0;
	}

	return $wrapped - 180.0;
  }

  /**
   * Split an ISO longitude pair into 1 or 2 intervals on [-180, 180].
   * west > east means the box crosses the antimeridian.
   *
   * @param float $west
   * @param float $east
   * @return array
   */
  function bounding_box_longitude_intervals($west, $east)
  {
	$west = wrap_longitude($west);
	$east = wrap_longitude($east);
	if ($west === NULL || $east === NULL)
	{
		return array();
	}

	if ($west <= $east)
	{
		return array(array($west, $east));
	}

	return array(array($west, 180.0), array(-180.0, $east));
  }

  /**
   * Merge overlapping [start, end] intervals.
   *
   * @param array $intervals
   * @return array
   */
  function merge_longitude_intervals($intervals)
  {
	if (empty($intervals))
	{
		return array();
	}

	usort($intervals, function ($a, $b) {
		if ($a[0] == $b[0])
		{
			return 0;
		}
		return ($a[0] < $b[0]) ? -1 : 1;
	});

	$merged = array(array($intervals[0][0], $intervals[0][1]));
	foreach ($intervals as $index => $interval)
	{
		if ($index === 0)
		{
			continue;
		}

		$last_index = count($merged) - 1;
		if ($interval[0] <= $merged[$last_index][1])
		{
			$merged[$last_index][1] = max($merged[$last_index][1], $interval[1]);
		}
		else
		{
			$merged[] = array($interval[0], $interval[1]);
		}
	}

	return $merged;
  }

  /**
   * Smallest-arc union of geographic bounding boxes.
   * Each box may use west/east/south/north or ISO *Bound* keys.
   * Result west > east means the union crosses the antimeridian.
   *
   * @param array $boxes
   * @return array|null
   */
  function union_geographic_bounding_boxes($boxes)
  {
	if (!is_array($boxes) || empty($boxes))
	{
		return NULL;
	}

	$intervals = array();
	$souths = array();
	$norths = array();

	foreach ($boxes as $box)
	{
		if (!is_array($box))
		{
			continue;
		}

		$west = isset($box['west']) ? $box['west'] : (isset($box['westBoundLongitude']) ? $box['westBoundLongitude'] : NULL);
		$east = isset($box['east']) ? $box['east'] : (isset($box['eastBoundLongitude']) ? $box['eastBoundLongitude'] : NULL);
		$south = isset($box['south']) ? $box['south'] : (isset($box['southBoundLatitude']) ? $box['southBoundLatitude'] : NULL);
		$north = isset($box['north']) ? $box['north'] : (isset($box['northBoundLatitude']) ? $box['northBoundLatitude'] : NULL);

		if (!is_numeric($west) || !is_numeric($east) || !is_numeric($south) || !is_numeric($north))
		{
			continue;
		}

		$west = wrap_longitude($west);
		$east = wrap_longitude($east);
		$south = (float) $south;
		$north = (float) $north;

		if ($west === NULL || $east === NULL
			|| $south < -90 || $south > 90 || $north < -90 || $north > 90 || $south > $north)
		{
			continue;
		}

		foreach (bounding_box_longitude_intervals($west, $east) as $interval)
		{
			$intervals[] = $interval;
		}
		$souths[] = $south;
		$norths[] = $north;
	}

	if (empty($intervals))
	{
		return NULL;
	}

	$merged = merge_longitude_intervals($intervals);
	$south_out = min($souths);
	$north_out = max($norths);

	if (count($merged) === 1 && $merged[0][0] <= -180 && $merged[0][1] >= 180)
	{
		return array(
			'westBoundLongitude' => -180.0,
			'eastBoundLongitude' => 180.0,
			'southBoundLatitude' => $south_out,
			'northBoundLatitude' => $north_out
		);
	}

	$largest_gap = -1.0;
	$largest_gap_after_index = count($merged) - 1;
	for ($k = 0; $k < count($merged) - 1; $k++)
	{
		$gap = $merged[$k + 1][0] - $merged[$k][1];
		if ($gap > $largest_gap)
		{
			$largest_gap = $gap;
			$largest_gap_after_index = $k;
		}
	}

	$wrap_gap = (180.0 - $merged[count($merged) - 1][1]) + ($merged[0][0] + 180.0);
	if ($wrap_gap > $largest_gap)
	{
		$largest_gap_after_index = count($merged) - 1;
	}

	if ($largest_gap_after_index === count($merged) - 1)
	{
		$west_out = $merged[0][0];
		$east_out = $merged[count($merged) - 1][1];
	}
	else
	{
		$west_out = $merged[$largest_gap_after_index + 1][0];
		$east_out = $merged[$largest_gap_after_index][1];
	}

	return array(
		'westBoundLongitude' => $west_out,
		'eastBoundLongitude' => $east_out,
		'southBoundLatitude' => $south_out,
		'northBoundLatitude' => $north_out
	);
  }

  // ------------------------------------------------------------------------

