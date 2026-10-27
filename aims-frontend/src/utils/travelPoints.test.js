import assert from 'node:assert/strict';
import test from 'node:test';
import { displayedTravelPoints, selectedTravelPoint } from './travelPoints.js';

test('a new trip begins with one departure point', () => {
  const points = [
    { id: 10, sequence: 1, event_type: 'departure', label: 'Departure', has_photo: false },
    { id: 11, sequence: 2, event_type: 'checkpoint', label: 'Checkpoint 1', has_photo: true },
  ];

  const displayed = displayedTravelPoints(points);
  assert.deepEqual(displayed.map((point) => [point.id, point.display_sequence]), [[10, 1], [11, 2]]);
  assert.equal(displayed[0].earlier_point, null);
  assert.equal(points[0].display_sequence, undefined);
});

test('an old trip shows departure first and retains the earlier photo separately', () => {
  const points = [
    { id: 20, sequence: 1, event_type: 'start', label: 'Starting Location', has_photo: true, photo_endpoint: '/legacy/photo', captured_at: '2026-10-19T15:16:00+08:00' },
    { id: 21, sequence: 2, event_type: 'departure', label: 'Departure', has_photo: false },
    { id: 22, sequence: 3, event_type: 'checkpoint', label: 'Checkpoint 1', has_photo: false },
  ];

  const displayed = displayedTravelPoints(points);
  assert.deepEqual(displayed.map((point) => [point.id, point.display_sequence]), [[21, 1], [22, 2]]);
  assert.equal(displayed[0].earlier_point.photo_endpoint, '/legacy/photo');
  assert.equal(displayed[0].has_photo, false);
  assert.equal(points.length, 3);
});

test('an unfinished old trip keeps its only GPS point until departure exists', () => {
  const displayed = displayedTravelPoints([{ id: 30, sequence: 1, event_type: 'start', label: 'Starting Location' }]);
  assert.deepEqual(displayed.map((point) => point.id), [30]);
});

test('photo review defaults to final arrival and lets a reviewer choose any route point', () => {
  const points = [
    { id: 1, event_type: 'departure', has_photo: true },
    { id: 2, event_type: 'checkpoint', has_photo: false },
    { id: 3, event_type: 'final_arrival', has_photo: true },
  ];
  assert.equal(selectedTravelPoint(points).id, 3);
  assert.equal(selectedTravelPoint(points, 1).id, 1);
  assert.equal(selectedTravelPoint(points, 2).id, 2);
  assert.equal(selectedTravelPoint(points, 999).id, 3);
  assert.equal(selectedTravelPoint(points, 1).has_photo, true);
});

test('photo review handles trips without arrival, removed selections, and empty routes', () => {
  const points = [{ id: 10, point_type: 'departure' }, { id: 11, point_type: 'final_arrival' }];
  assert.equal(selectedTravelPoint(points).id, 11);
  assert.equal(selectedTravelPoint(points.slice(0, 1), 11).id, 10);
  assert.equal(selectedTravelPoint([], 11), null);
  assert.equal(selectedTravelPoint(), null);
  assert.deepEqual(points, [{ id: 10, point_type: 'departure' }, { id: 11, point_type: 'final_arrival' }]);
});
