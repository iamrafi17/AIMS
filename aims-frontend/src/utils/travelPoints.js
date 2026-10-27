const pointType = (point) => point.event_type || point.point_type;

export function selectedTravelPoint(points = [], selectedId = null) {
  return points.find((point) => point.id === selectedId)
    || points.find((point) => pointType(point) === 'final_arrival')
    || points[0]
    || null;
}

export function displayedTravelPoints(points = []) {
  const ordered = [...points].sort((first, second) => Number(first.sequence) - Number(second.sequence));
  const departure = ordered.find((point) => pointType(point) === 'departure');
  const earlierPoint = departure ? ordered.find((point) => pointType(point) === 'start') || null : null;

  return ordered
    .filter((point) => !(departure && pointType(point) === 'start'))
    .map((point, index) => ({
      ...point,
      display_sequence: index + 1,
      earlier_point: point.id === departure?.id ? earlierPoint : null,
    }));
}
