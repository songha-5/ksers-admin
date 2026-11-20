<?php

// Program at a Glance tables for Railway MySQL.
// Column names match the Supabase draft. Differences the homepage must follow:
// - every table has year; read and write with the congress year
// - primary key is (year, code) or (year, id), not code/id alone
// - there is no public schema, RLS, or anon key
// - use program_sessions (plural). program_session is the speaker admin table

function ksers_glance_colors() {
    return [
        'gray',
        'light-green',
        'purple',
        'light-blue',
        'green',
        'red',
        'orange',
        'indigo',
        'pink',
        'dark-yellow',
        'mint',
        'blue',
        'none',
    ];
}

function ksers_glance_color_names() {
    return [
        'gray' => t('회색', 'Gray'),
        'light-green' => t('연두', 'Light green'),
        'purple' => t('보라', 'Purple'),
        'light-blue' => t('하늘', 'Light blue'),
        'green' => t('초록', 'Green'),
        'red' => t('빨강', 'Red'),
        'orange' => t('주황', 'Orange'),
        'indigo' => t('남색', 'Indigo'),
        'pink' => t('분홍', 'Pink'),
        'dark-yellow' => t('노랑', 'Yellow'),
        'mint' => t('민트', 'Mint'),
        'blue' => t('파랑', 'Blue'),
        'none' => t('없음', 'None'),
    ];
}

function ksers_glance_ensure_tables($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_rooms (
            year smallint NOT NULL,
            code varchar(40) NOT NULL,
            floor varchar(80) NOT NULL,
            label varchar(120) NOT NULL,
            venue_name varchar(120) DEFAULT NULL,
            sort_order int NOT NULL,
            PRIMARY KEY (year, code),
            KEY program_rooms_sort_order_idx (year, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_days (
            year smallint NOT NULL,
            id varchar(80) NOT NULL,
            label varchar(160) NOT NULL,
            sort_order int NOT NULL,
            PRIMARY KEY (year, id),
            KEY program_days_sort_order_idx (year, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_time_slots (
            year smallint NOT NULL,
            id varchar(80) NOT NULL,
            day_id varchar(80) NOT NULL,
            time_label varchar(80) NOT NULL,
            sort_order int NOT NULL,
            PRIMARY KEY (year, id),
            KEY program_time_slots_day_id_idx (year, day_id, sort_order),
            CONSTRAINT fk_program_time_slots_day
                FOREIGN KEY (year, day_id) REFERENCES program_days (year, id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
    $colorList = "'" . implode("','", ksers_glance_colors()) . "'";
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS program_sessions (
            year smallint NOT NULL,
            id varchar(80) NOT NULL,
            slot_id varchar(80) NOT NULL,
            room_code varchar(40) NOT NULL,
            column_span int NOT NULL DEFAULT 1,
            row_span int NOT NULL DEFAULT 1,
            title varchar(255) DEFAULT NULL,
            content text,
            topic varchar(255) DEFAULT NULL,
            href varchar(500) DEFAULT NULL,
            label varchar(80) DEFAULT NULL,
            color varchar(20) NOT NULL DEFAULT 'none',
            sort_order int NOT NULL,
            PRIMARY KEY (year, id),
            KEY program_sessions_slot_id_idx (year, slot_id, sort_order),
            CONSTRAINT fk_program_sessions_slot
                FOREIGN KEY (year, slot_id) REFERENCES program_time_slots (year, id)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_program_sessions_room
                FOREIGN KEY (year, room_code) REFERENCES program_rooms (year, code)
                ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT chk_program_sessions_span CHECK (column_span >= 1 AND row_span >= 1),
            CONSTRAINT chk_program_sessions_color CHECK (color IN ($colorList))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
    );
}

function ksers_glance_blank($value) {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function ksers_glance_known_list($items) {
    if (!$items) {
        return '';
    }
    $lines = [];
    foreach ($items as $item) {
        $line = h($item['id']);
        if ($item['label'] !== '') {
            $line .= ' · ' . h($item['label']);
        }
        $lines[] = $line;
    }
    return '<details class="glance-known"><summary>'
        . h(t('등록된 구분값', 'Existing ids')) . ' ' . count($items)
        . '</summary><div>' . implode('<br>', $lines) . '</div></details>';
}

function ksers_glance_new_id_input($name, $placeholder, $taken, $value = '') {
    $taken = array_values(array_filter($taken, function ($item) {
        return $item !== '';
    }));
    return '<input class="form-control" name="' . h($name) . '" value="' . h($value) . '" required placeholder="' . h($placeholder) . '" data-taken="'
        . h(implode(',', $taken)) . '" autocomplete="off">';
}

function ksers_glance_valid_key($value, $max) {
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,' . ((int)$max - 1) . '}$/', (string)$value);
}

function ksers_glance_safe_href($href) {
    $href = trim((string)$href);
    if ($href === '' || preg_match('/[\s<>"\']/', $href)) {
        return '';
    }
    if (preg_match('#^(https?://|/)#i', $href)) {
        return $href;
    }
    return '';
}

function ksers_glance_next_sort($rows) {
    $max = 0;
    foreach ($rows as $row) {
        $max = max($max, (int)$row['sort_order']);
    }
    return $max + 1;
}

function ksers_glance_load($pdo, $year) {
    $year = (int)$year;
    $rooms = $pdo->prepare(
        'SELECT code, floor, label, venue_name, sort_order
         FROM program_rooms WHERE year = ? ORDER BY sort_order, code'
    );
    $rooms->execute([$year]);
    $days = $pdo->prepare(
        'SELECT id, label, sort_order FROM program_days WHERE year = ? ORDER BY sort_order, id'
    );
    $days->execute([$year]);
    $slots = $pdo->prepare(
        'SELECT id, day_id, time_label, sort_order
         FROM program_time_slots WHERE year = ? ORDER BY sort_order, id'
    );
    $slots->execute([$year]);
    $sessions = $pdo->prepare(
        'SELECT id, slot_id, room_code, column_span, row_span, title, content, topic, href, label, color, sort_order
         FROM program_sessions WHERE year = ? ORDER BY sort_order, id'
    );
    $sessions->execute([$year]);

    $roomRows = $rooms->fetchAll(PDO::FETCH_ASSOC);
    $dayRows = $days->fetchAll(PDO::FETCH_ASSOC);
    $slotRows = $slots->fetchAll(PDO::FETCH_ASSOC);
    $sessionRows = $sessions->fetchAll(PDO::FETCH_ASSOC);
    $slotsByDay = [];
    foreach ($slotRows as $slot) {
        $slotsByDay[$slot['day_id']][] = $slot;
    }
    $sessionsBySlot = [];
    foreach ($sessionRows as $session) {
        $sessionsBySlot[$session['slot_id']][] = $session;
    }
    return [
        'rooms' => $roomRows,
        'days' => $dayRows,
        'slots' => $slotRows,
        'sessions' => $sessionRows,
        'slotsByDay' => $slotsByDay,
        'sessionsBySlot' => $sessionsBySlot,
    ];
}

function ksers_glance_floor_groups($rooms) {
    $groups = [];
    foreach ($rooms as $room) {
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last]['floor'] === $room['floor']) {
            $groups[$last]['span']++;
            continue;
        }
        $groups[] = ['floor' => $room['floor'], 'span' => 1];
    }
    return $groups;
}

function ksers_glance_grid($rooms, $slots, $sessions) {
    $roomIndex = [];
    foreach ($rooms as $index => $room) {
        $roomIndex[$room['code']] = $index;
    }
    $slotIndex = [];
    foreach ($slots as $index => $slot) {
        $slotIndex[$slot['id']] = $index;
    }
    $rowCount = count($slots);
    $colCount = count($rooms);
    $cells = [];
    $skip = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $cells[$row] = array_fill(0, $colCount, null);
        $skip[$row] = array_fill(0, $colCount, false);
    }
    $warnings = [];
    $ordered = $sessions;
    usort($ordered, function ($a, $b) {
        $sort = (int)$a['sort_order'] - (int)$b['sort_order'];
        if ($sort !== 0) {
            return $sort;
        }
        return strcmp((string)$a['id'], (string)$b['id']);
    });
    foreach ($ordered as $session) {
        if (!isset($slotIndex[$session['slot_id']]) || !isset($roomIndex[$session['room_code']])) {
            $warnings[] = $session['id'];
            continue;
        }
        $row = $slotIndex[$session['slot_id']];
        $col = $roomIndex[$session['room_code']];
        $rowSpan = max(1, (int)$session['row_span']);
        $colSpan = max(1, (int)$session['column_span']);
        $fits = true;
        for ($dRow = 0; $dRow < $rowSpan; $dRow++) {
            for ($dCol = 0; $dCol < $colSpan; $dCol++) {
                $targetRow = $row + $dRow;
                $targetCol = $col + $dCol;
                if ($targetRow >= $rowCount || $targetCol >= $colCount || $skip[$targetRow][$targetCol] || $cells[$targetRow][$targetCol]) {
                    $fits = false;
                }
            }
        }
        if (!$fits) {
            $warnings[] = $session['id'];
            continue;
        }
        for ($dRow = 0; $dRow < $rowSpan; $dRow++) {
            for ($dCol = 0; $dCol < $colSpan; $dCol++) {
                if ($dRow === 0 && $dCol === 0) {
                    continue;
                }
                $skip[$row + $dRow][$col + $dCol] = true;
            }
        }
        $cells[$row][$col] = $session;
    }
    return ['cells' => $cells, 'skip' => $skip, 'warnings' => $warnings];
}

function ksers_glance_render_cell($session) {
    $title = trim((string)$session['title']);
    $content = trim((string)$session['content']);
    $topic = trim((string)$session['topic']);
    $label = trim((string)$session['label']);
    $href = ksers_glance_safe_href(isset($session['href']) ? $session['href'] : '');
    $html = '';
    if ($label !== '') {
        $html .= '<span class="glance-badge">' . h($label) . '</span>';
    }
    if ($title !== '') {
        if ($content === '' && $href !== '') {
            $html .= '<a class="glance-title glance-link" href="' . h($href) . '">' . h($title) . '</a>';
        } else {
            $html .= '<div class="glance-title">' . h($title) . '</div>';
        }
    }
    if ($content !== '') {
        if ($href !== '') {
            $html .= '<a class="glance-content glance-link" href="' . h($href) . '">' . h($content) . '</a>';
        } else {
            $html .= '<div class="glance-content">' . h($content) . '</div>';
        }
    }
    if ($topic !== '') {
        $html .= '<div class="glance-topic">' . h($topic) . '</div>';
    }
    if ($html === '') {
        $html = '<span class="glance-topic">' . h(t('빈 글', 'Empty text')) . '</span>';
    }
    return $html;
}

function ksers_glance_place_issue($rooms, $slots, $sessions, $candidate) {
    $roomIndex = [];
    foreach ($rooms as $index => $room) {
        $roomIndex[$room['code']] = $index;
    }
    $slotIndex = [];
    foreach ($slots as $index => $slot) {
        $slotIndex[$slot['id']] = $index;
    }
    if (!isset($roomIndex[$candidate['room_code']]) || !isset($slotIndex[$candidate['slot_id']])) {
        return t(
            '저장하지 않았습니다. 선택한 룸이나 시간이 이 날짜 표에 없습니다.',
            'Not saved. The chosen room or time is not on this day.'
        );
    }
    $row = $slotIndex[$candidate['slot_id']];
    $col = $roomIndex[$candidate['room_code']];
    $rowSpan = max(1, (int)$candidate['row_span']);
    $colSpan = max(1, (int)$candidate['column_span']);
    $roomCount = count($rooms);
    $timeCount = count($slots);
    $roomNo = $col + 1;
    $timeNo = $row + 1;
    $maxAcross = $roomCount - $col;
    $maxDown = $timeCount - $row;
    $reasons = [];
    if ($colSpan > $maxAcross) {
        $reasons[] = t(
            '가로는 룸 ' . $roomCount . '칸입니다. ' . $roomNo . '번째 룸에서 가로 ' . $colSpan . '칸을 덮으면 표를 넘어갑니다. 가로 칸 수는 ' . $maxAcross . '까지 넣을 수 있습니다.',
            'There are ' . $roomCount . ' rooms across. Starting at room ' . $roomNo . ' and covering ' . $colSpan . ' columns goes past the table. Across can be at most ' . $maxAcross . '.'
        );
    }
    if ($rowSpan > $maxDown) {
        $reasons[] = t(
            '이 날짜의 시간은 ' . $timeCount . '줄입니다. ' . $timeNo . '번째 시간에서 아래로 ' . $rowSpan . '줄을 덮으면 표를 넘어갑니다. 세로 칸 수는 ' . $maxDown . '까지 넣을 수 있습니다.',
            'This day has ' . $timeCount . ' time rows. Starting at row ' . $timeNo . ' and covering ' . $rowSpan . ' rows goes past the table. Down can be at most ' . $maxDown . '.'
        );
    }
    if ($reasons) {
        return t(
            '저장하지 않았습니다. 칸이 표 밖으로 나가서 시간표에 반영되지 않습니다.',
            'Not saved. The cell goes outside the timetable, so it was not applied.'
        ) . ' ' . implode(' ', $reasons);
    }
    foreach ($sessions as $session) {
        if (!isset($roomIndex[$session['room_code']]) || !isset($slotIndex[$session['slot_id']])) {
            continue;
        }
        $otherRow = $slotIndex[$session['slot_id']];
        $otherCol = $roomIndex[$session['room_code']];
        $otherRowSpan = max(1, (int)$session['row_span']);
        $otherColSpan = max(1, (int)$session['column_span']);
        $rowsOverlap = $row < $otherRow + $otherRowSpan && $otherRow < $row + $rowSpan;
        $colsOverlap = $col < $otherCol + $otherColSpan && $otherCol < $col + $colSpan;
        if ($rowsOverlap && $colsOverlap) {
            $name = trim((string)$session['title']);
            if ($name === '') {
                $name = (string)$session['id'];
            }
            return t(
                '저장하지 않았습니다. 덮으려는 자리에 이미 「' . $name . '」 칸이 있습니다. 같은 자리를 두 칸이 함께 쓸 수 없어서 시간표에 반영되지 않습니다. 가로·세로를 줄이거나 빈 칸에서 시작하세요.',
                'Not saved. That space is already used by “' . $name . '”. Two cells cannot share the same spot, so this change was not applied. Reduce the span or start in an empty cell.'
            );
        }
    }
    return '';
}

function ksers_glance_color_class($color) {
    if (!in_array($color, ksers_glance_colors(), true)) {
        $color = 'none';
    }
    return 'glance-c-' . $color;
}

function ksers_glance_move_form($kind, $id, $direction, $symbol, $day) {
    return '<form method="post" action="action.php" class="d-inline">'
        . '<input type="hidden" name="action" value="move_' . h($kind) . '">'
        . '<input type="hidden" name="id" value="' . h($id) . '">'
        . '<input type="hidden" name="direction" value="' . h($direction) . '">'
        . '<input type="hidden" name="return_day" value="' . h($day) . '">'
        . '<button class="glance-move" type="submit">' . h($symbol) . '</button>'
        . '</form>';
}

function ksers_glance_move($pdo, $year, $kind, $id, $direction) {
    $year = (int)$year;
    $id = (string)$id;
    if ($kind === 'room') {
        $stmt = $pdo->prepare('SELECT code AS item_id FROM program_rooms WHERE year = ? ORDER BY sort_order, code');
        $stmt->execute([$year]);
        $table = 'program_rooms';
        $column = 'code';
        $step = ($direction === 'left') ? -1 : (($direction === 'right') ? 1 : 0);
    } elseif ($kind === 'day') {
        $stmt = $pdo->prepare('SELECT id AS item_id FROM program_days WHERE year = ? ORDER BY sort_order, id');
        $stmt->execute([$year]);
        $table = 'program_days';
        $column = 'id';
        $step = ($direction === 'left') ? -1 : (($direction === 'right') ? 1 : 0);
    } elseif ($kind === 'slot') {
        $parent = $pdo->prepare('SELECT day_id FROM program_time_slots WHERE year = ? AND id = ?');
        $parent->execute([$year, $id]);
        $dayId = $parent->fetchColumn();
        if (!$dayId) {
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT id AS item_id FROM program_time_slots WHERE year = ? AND day_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$year, $dayId]);
        $table = 'program_time_slots';
        $column = 'id';
        $step = ($direction === 'up') ? -1 : (($direction === 'down') ? 1 : 0);
    } else {
        return;
    }
    if ($step === 0) {
        return;
    }
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $index = array_search($id, $ids, true);
    $swap = $index === false ? -1 : $index + $step;
    if ($index === false || $swap < 0 || $swap >= count($ids)) {
        return;
    }
    $current = $ids[$index];
    $ids[$index] = $ids[$swap];
    $ids[$swap] = $current;
    $pdo->beginTransaction();
    $update = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE year = ? AND {$column} = ?");
    foreach ($ids as $order => $itemId) {
        $update->execute([$order + 1, $year, $itemId]);
    }
    $pdo->commit();
}

function ksers_glance_example() {
    $href = function ($anchor) {
        return '/2027/en/scientific-program#' . $anchor;
    };
    return [
        'rooms' => [
            ['A', '2F', 'Room A', 'Crystal 1', 1],
            ['B', '2F', 'Room B', 'Crystal 2', 2],
            ['C', '2F', 'Room C', 'Crystal 3', 3],
            ['D', '2F', 'Room D', 'Emerald', 4],
            ['E', '3F', 'Room E', 'Sapphire', 5],
        ],
        'days' => [
            ['april-22', 'April 22 (Thu)', 1],
            ['april-23', 'April 23 (Fri)', 2],
            ['april-24', 'April 24 (Sat)', 3],
        ],
        'slots' => [
            ['apr22-0830', 'april-22', '08:30-09:30', 1],
            ['apr22-0930', 'april-22', '09:30-09:50', 2],
            ['apr22-0950', 'april-22', '09:50-11:00', 3],
            ['apr22-1100', 'april-22', '11:00-11:20', 4],
            ['apr22-1120', 'april-22', '11:20-11:40', 5],
            ['apr22-1140', 'april-22', '11:40-12:10', 6],
            ['apr22-1210', 'april-22', '12:10-13:00', 7],
            ['apr22-1300', 'april-22', '13:00-14:20', 8],
        ],
        'sessions' => [
            ['apr22-vp01', 'apr22-0830', 'A', 1, 1, 'VP01', 'Video Presentation 1', 'UGI', $href('vp01'), null, 'orange', 1],
            ['apr22-vp02', 'apr22-0830', 'B', 1, 1, 'VP02', 'Video Presentation 2', 'HBP', $href('vp02'), null, 'orange', 2],
            ['apr22-vp03', 'apr22-0830', 'C', 1, 1, 'VP03', 'Video Presentation 3', 'LGI', $href('vp03'), null, 'orange', 3],
            ['apr22-vp04', 'apr22-0830', 'D', 1, 1, 'VP04', 'Video Presentation 4', 'ENDO', $href('vp04'), null, 'orange', 4],
            ['apr22-vp05', 'apr22-0830', 'E', 1, 1, 'VP05', 'Video Presentation 5', 'Pediatric/Hernia', $href('vp05'), null, 'orange', 5],
            ['apr22-coffee-1', 'apr22-0930', 'A', 5, 1, 'Coffee Break (20\')', null, null, null, null, 'gray', 1],
            ['apr22-sy01', 'apr22-0950', 'A', 1, 1, 'SY01', 'Symposium 01', 'UGI', $href('sy01'), null, 'light-blue', 1],
            ['apr22-js01', 'apr22-0950', 'B', 1, 1, 'JS01', 'KSERS - SAGES Joint Symposium', null, $href('js01'), null, 'purple', 2],
            ['apr22-sy02', 'apr22-0950', 'C', 1, 1, 'SY02', 'Symposium 02', '(LGI)', $href('sy02'), null, 'light-blue', 3],
            ['apr22-sy03', 'apr22-0950', 'D', 1, 1, 'SY03', 'Symposium 03', '(ENDO)', $href('sy03'), null, 'light-blue', 4],
            ['apr22-sy04', 'apr22-0950', 'E', 1, 1, 'SY04', 'Symposium 04', '(Pediatric/Hernia)', $href('sy04'), null, 'light-blue', 5],
            ['apr22-coffee-2', 'apr22-1100', 'A', 5, 1, 'Coffee Break (20\')', null, null, null, null, 'gray', 1],
            ['apr22-opening', 'apr22-1120', 'A', 3, 1, 'Opening Ceremony', null, null, null, null, 'light-green', 1],
            ['apr22-keynote', 'apr22-1140', 'A', 3, 1, 'Keynote Lecture', null, null, $href('keynote'), null, 'pink', 1],
            ['apr22-ls01', 'apr22-1210', 'A', 3, 1, 'LS01', 'Luncheon Symposium 1 (TBA)', null, $href('ls01'), null, 'orange', 1],
            ['apr22-ls02', 'apr22-1210', 'D', 2, 1, 'LS02', 'Luncheon Symposium 2 (TBA)', null, $href('ls02'), null, 'orange', 2],
            ['apr22-cs01', 'apr22-1300', 'A', 3, 1, 'CS01', 'Co-Symposium 1', '(The next frontier in minimally invasive surgery;Emerging Technologies)', $href('cs01'), null, 'light-blue', 1],
        ],
    ];
}

function ksers_glance_load_example($pdo, $year) {
    $year = (int)$year;
    $example = ksers_glance_example();
    $added = 0;
    $pdo->beginTransaction();
    $roomExists = $pdo->prepare('SELECT 1 FROM program_rooms WHERE year = ? AND code = ?');
    $roomInsert = $pdo->prepare(
        'INSERT INTO program_rooms (year, code, floor, label, venue_name, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($example['rooms'] as $row) {
        $roomExists->execute([$year, $row[0]]);
        if ($roomExists->fetchColumn()) {
            continue;
        }
        $roomInsert->execute([$year, $row[0], $row[1], $row[2], $row[3], $row[4]]);
        $added++;
    }
    $dayExists = $pdo->prepare('SELECT 1 FROM program_days WHERE year = ? AND id = ?');
    $dayInsert = $pdo->prepare('INSERT INTO program_days (year, id, label, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($example['days'] as $row) {
        $dayExists->execute([$year, $row[0]]);
        if ($dayExists->fetchColumn()) {
            continue;
        }
        $dayInsert->execute([$year, $row[0], $row[1], $row[2]]);
        $added++;
    }
    $slotExists = $pdo->prepare('SELECT 1 FROM program_time_slots WHERE year = ? AND id = ?');
    $slotInsert = $pdo->prepare(
        'INSERT INTO program_time_slots (year, id, day_id, time_label, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($example['slots'] as $row) {
        $slotExists->execute([$year, $row[0]]);
        if ($slotExists->fetchColumn()) {
            continue;
        }
        $slotInsert->execute([$year, $row[0], $row[1], $row[2], $row[3]]);
        $added++;
    }
    $sessionExists = $pdo->prepare('SELECT 1 FROM program_sessions WHERE year = ? AND id = ?');
    $sessionInsert = $pdo->prepare(
        'INSERT INTO program_sessions
            (year, id, slot_id, room_code, column_span, row_span, title, content, topic, href, label, color, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($example['sessions'] as $row) {
        $sessionExists->execute([$year, $row[0]]);
        if ($sessionExists->fetchColumn()) {
            continue;
        }
        $sessionInsert->execute([
            $year, $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11],
        ]);
        $added++;
    }
    $pdo->commit();
    return $added;
}

function ksers_glance_zip_text($path, $name) {
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 22) {
        return null;
    }
    $tailLen = min(strlen($data), 66000);
    $tail = substr($data, -$tailLen);
    $rel = strrpos($tail, "PK\x05\x06");
    if ($rel === false) {
        return null;
    }
    $eocd = strlen($data) - $tailLen + $rel;
    $cdSize = unpack('V', substr($data, $eocd + 12, 4))[1];
    $cdOffset = unpack('V', substr($data, $eocd + 16, 4))[1];
    $pos = $cdOffset;
    $end = $cdOffset + $cdSize;
    while ($pos + 46 <= $end && substr($data, $pos, 4) === "PK\x01\x02") {
        $method = unpack('v', substr($data, $pos + 10, 2))[1];
        $compSize = unpack('V', substr($data, $pos + 20, 4))[1];
        $nameLen = unpack('v', substr($data, $pos + 28, 2))[1];
        $extraLen = unpack('v', substr($data, $pos + 30, 2))[1];
        $commentLen = unpack('v', substr($data, $pos + 32, 2))[1];
        $localOff = unpack('V', substr($data, $pos + 42, 4))[1];
        $entryName = substr($data, $pos + 46, $nameLen);
        $pos += 46 + $nameLen + $extraLen + $commentLen;
        if ($entryName !== $name) {
            continue;
        }
        if (substr($data, $localOff, 4) !== "PK\x03\x04") {
            return null;
        }
        $localNameLen = unpack('v', substr($data, $localOff + 26, 2))[1];
        $localExtraLen = unpack('v', substr($data, $localOff + 28, 2))[1];
        $start = $localOff + 30 + $localNameLen + $localExtraLen;
        $blob = substr($data, $start, $compSize);
        if ($method === 0) {
            return $blob;
        }
        if ($method === 8) {
            $text = gzinflate($blob);
            return $text === false ? null : $text;
        }
        return null;
    }
    return null;
}

function ksers_glance_schedule_lines($text) {
    $text = str_replace(["\r\n", "\r"], "\n", trim((string)$text));
    if ($text === '') {
        return [];
    }
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}

function ksers_glance_schedule_cut($text, $max) {
    if ($text === null) {
        return null;
    }
    $max = (int)$max;
    if (preg_match('/^.{0,' . $max . '}/us', $text, $match)) {
        return $match[0];
    }
    return $text;
}

function ksers_glance_schedule_slug($text, $max) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');
    if ($text === '') {
        $text = 'item';
    }
    if (strlen($text) > $max) {
        $text = rtrim(substr($text, 0, $max), '-');
    }
    return $text === '' ? 'item' : $text;
}

function ksers_glance_schedule_unique_id(&$used, $base, $max) {
    $base = ksers_glance_schedule_slug($base, $max);
    $id = $base;
    $n = 2;
    while (isset($used[$id])) {
        $suffix = '-' . $n;
        $id = rtrim(substr($base, 0, $max - strlen($suffix)), '-') . $suffix;
        $n++;
    }
    $used[$id] = true;
    return $id;
}

function ksers_glance_schedule_time($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (is_numeric($value)) {
        $fraction = (float)$value;
        $fraction = $fraction - floor($fraction);
        $minutes = (int)round($fraction * 24 * 60);
        if ($minutes < 0 || $minutes >= 24 * 60) {
            return '';
        }
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $match)) {
        $hour = (int)$match[1];
        $minute = (int)$match[2];
        if ($hour > 23 || $minute > 59) {
            return '';
        }
        return sprintf('%02d:%02d', $hour, $minute);
    }
    return '';
}

function ksers_glance_schedule_minutes($time) {
    $parts = explode(':', $time);
    return ((int)$parts[0]) * 60 + (int)$parts[1];
}

function ksers_glance_schedule_color($raw) {
    $key = strtolower(trim((string)$raw));
    $key = preg_replace('/[^a-z]+/', '-', $key);
    $key = trim((string)$key, '-');
    $map = [
        '' => 'none',
        'peach' => 'orange',
        'orange' => 'orange',
        'break' => 'gray',
        'gray' => 'gray',
        'grey' => 'gray',
        'sky' => 'light-blue',
        'light-blue' => 'light-blue',
        'lavender' => 'purple',
        'purple' => 'purple',
        'green' => 'green',
        'light-green' => 'light-green',
        'pink' => 'pink',
        'mint' => 'mint',
        'red' => 'red',
        'indigo' => 'indigo',
        'blue' => 'blue',
        'yellow' => 'dark-yellow',
        'dark-yellow' => 'dark-yellow',
        'none' => 'none',
    ];
    if (!isset($map[$key])) {
        return null;
    }
    return $map[$key];
}

function ksers_glance_sheet_rows($xml, $strings) {
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }
    $rows = [];
    foreach ($dom->getElementsByTagName('row') as $row) {
        $cells = [];
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof DOMElement || $cell->localName !== 'c') {
                continue;
            }
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($cell->getAttribute('r')));
            $col = 0;
            $len = strlen($letters);
            for ($i = 0; $i < $len; $i++) {
                $col = $col * 26 + (ord($letters[$i]) - 64);
            }
            $type = $cell->getAttribute('t');
            $value = '';
            if ($type === 'inlineStr') {
                foreach ($cell->getElementsByTagName('t') as $text) {
                    $value .= $text->textContent;
                }
            } else {
                $raw = '';
                foreach ($cell->childNodes as $child) {
                    if ($child instanceof DOMElement && $child->localName === 'v') {
                        $raw = $child->textContent;
                        break;
                    }
                }
                if ($type === 's') {
                    $value = isset($strings[(int)$raw]) ? $strings[(int)$raw] : '';
                } else {
                    $value = $raw;
                }
            }
            $cells[$col] = $value;
        }
        $rows[(int)$row->getAttribute('r')] = $cells;
    }
    return $rows;
}

function ksers_glance_shared_strings($xml) {
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }
    $strings = [];
    foreach ($dom->getElementsByTagName('si') as $item) {
        $text = '';
        foreach ($item->getElementsByTagName('t') as $node) {
            $text .= $node->textContent;
        }
        $strings[] = $text;
    }
    return $strings;
}

function ksers_glance_workbook_map($path) {
    $workbook = ksers_glance_zip_text($path, 'xl/workbook.xml');
    $rels = ksers_glance_zip_text($path, 'xl/_rels/workbook.xml.rels');
    if ($workbook === null || $rels === null) {
        return [];
    }
    $relDom = new DOMDocument();
    $bookDom = new DOMDocument();
    if (!@$relDom->loadXML($rels) || !@$bookDom->loadXML($workbook)) {
        return [];
    }
    $targets = [];
    foreach ($relDom->getElementsByTagName('Relationship') as $rel) {
        $target = $rel->getAttribute('Target');
        $target = ltrim(str_replace('\\', '/', $target), '/');
        if (strpos($target, 'xl/') !== 0) {
            $target = 'xl/' . $target;
        }
        $targets[$rel->getAttribute('Id')] = $target;
    }
    $sheets = [];
    foreach ($bookDom->getElementsByTagName('sheet') as $sheet) {
        $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
        if ($rid === '' || !isset($targets[$rid])) {
            continue;
        }
        $sheets[$sheet->getAttribute('name')] = $targets[$rid];
    }
    return $sheets;
}

function ksers_glance_schedule_room_parts($roomText) {
    $roomText = trim((string)$roomText);
    if ($roomText === '' || preg_match('/^(all|전체)$/iu', $roomText)) {
        return [];
    }
    if (preg_match('/[~～]/u', $roomText)) {
        $parts = preg_split('/\s*[~～]\s*/u', $roomText);
    } elseif (preg_match('/[,，、]/u', $roomText)) {
        $parts = preg_split('/\s*[,，、]\s*/u', $roomText);
    } else {
        return [$roomText];
    }
    $names = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') {
            $names[] = $part;
        }
    }
    return $names;
}

function ksers_glance_schedule_take_room($label, $sort, &$used) {
    $room = ksers_glance_schedule_new_room($label, $sort);
    $code = $room['code'];
    if ($code === 'item' || isset($used[$code])) {
        $n = 1;
        do {
            $code = 'r' . $n;
            $n++;
        } while (isset($used[$code]));
        $room['code'] = $code;
    }
    $used[$code] = true;
    return $room;
}

function ksers_glance_schedule_new_room($value, $sort) {
    $lines = ksers_glance_schedule_lines($value);
    $label = $lines ? $lines[0] : trim((string)$value);
    $venue = null;
    if (isset($lines[1]) && preg_match('/^\(?([^)]+)\)?$/', $lines[1], $match)) {
        $venue = trim($match[1]);
    }
    $code = $label;
    if (preg_match('/^room\s*([A-Za-z])$/i', $label, $match) || preg_match('/^([A-Za-z])$/', $label, $match)) {
        $code = strtoupper($match[1]);
        $label = 'Room ' . $code;
    } elseif (preg_match('/^room\s+([A-Za-z0-9][A-Za-z0-9_-]{0,20})$/i', $label, $match)) {
        $code = strlen($match[1]) === 1 ? strtoupper($match[1]) : $match[1];
        $label = 'Room ' . $code;
    } else {
        $code = ksers_glance_schedule_slug($label, 40);
    }
    return [
        'code' => $code,
        'floor' => '—',
        'label' => ksers_glance_schedule_cut($label, 120),
        'venue_name' => $venue === null ? null : ksers_glance_schedule_cut($venue, 120),
        'sort_order' => $sort,
    ];
}

function ksers_glance_schedule_rooms_from_sheet($rows, $allowPlain = false) {
    $headerRow = 0;
    $headerCells = [];
    $plain = false;
    foreach ($rows as $number => $cells) {
        foreach ($cells as $value) {
            $lines = ksers_glance_schedule_lines($value);
            $line = $lines ? $lines[0] : '';
            if (preg_match('/^room\s+/i', $line)) {
                $headerRow = $number;
                $headerCells = $cells;
                break 2;
            }
        }
    }
    if ($headerRow === 0 && $allowPlain) {
        $bestCount = 0;
        foreach ($rows as $number => $cells) {
            $count = 0;
            foreach ($cells as $value) {
                if (trim((string)$value) !== '') {
                    $count++;
                }
            }
            if ($count >= 2 && $count >= $bestCount) {
                $headerRow = $number;
                $headerCells = $cells;
                $bestCount = $count;
                $plain = true;
            }
        }
    }
    if ($headerRow === 0) {
        return [];
    }
    $floorRow = isset($rows[$headerRow - 1]) ? $rows[$headerRow - 1] : [];
    $rooms = [];
    $used = [];
    $columns = array_keys($headerCells);
    sort($columns, SORT_NUMERIC);
    foreach ($columns as $column) {
        $lines = ksers_glance_schedule_lines($headerCells[$column]);
        $line = $lines ? $lines[0] : '';
        if ($line === '' || (!$plain && !preg_match('/^room\s+/i', $line))) {
            continue;
        }
        $floor = '—';
        for ($cursor = $column; $cursor >= 1; $cursor--) {
            if (!isset($floorRow[$cursor])) {
                continue;
            }
            $floorLines = ksers_glance_schedule_lines($floorRow[$cursor]);
            $floorText = $floorLines ? $floorLines[0] : '';
            if ($floorText !== '' && !preg_match('/^time$/i', $floorText)) {
                $floor = $floorText;
                break;
            }
        }
        $room = ksers_glance_schedule_take_room($headerCells[$column], count($rooms) + 1, $used);
        $room['floor'] = ksers_glance_schedule_cut($floor, 80);
        $rooms[] = $room;
    }
    return $rooms;
}

function ksers_glance_schedule_find_room($name, $rooms) {
    $name = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', (string)$name)));
    foreach ($rooms as $room) {
        if (strcasecmp($room['label'], $name) === 0 || strcasecmp($room['code'], $name) === 0) {
            return $room['code'];
        }
    }
    if (preg_match('/^room\s*([A-Za-z])$/i', $name, $match) || preg_match('/^([A-Za-z])$/', $name, $match)) {
        $letter = strtoupper($match[1]);
        foreach ($rooms as $room) {
            if (strcasecmp($room['code'], $letter) === 0) {
                return $room['code'];
            }
        }
    }
    if (preg_match('/^room\s+(.+)$/i', $name, $match)) {
        $tail = trim($match[1]);
        foreach ($rooms as $room) {
            if (strcasecmp($room['code'], $tail) === 0) {
                return $room['code'];
            }
        }
    }
    return '';
}

function ksers_glance_parse_schedule_xlsx($path) {
    $empty = [
        'error' => '',
        'warnings' => [],
        'rooms' => [],
        'days' => [],
        'slots' => [],
        'sessions' => [],
        'first_day' => '',
    ];
    $sheets = ksers_glance_workbook_map($path);
    $schedulePath = '';
    $titles = [];
    foreach ($sheets as $name => $target) {
        if (strcasecmp($name, 'Schedule_Input') === 0) {
            $schedulePath = $target;
        }
    }
    if ($schedulePath === '') {
        $empty['error'] = t('Schedule_Input 시트가 없습니다.', 'The Schedule_Input sheet is missing.');
        return $empty;
    }
    $sharedXml = ksers_glance_zip_text($path, 'xl/sharedStrings.xml');
    $strings = $sharedXml === null ? [] : ksers_glance_shared_strings($sharedXml);
    $scheduleXml = ksers_glance_zip_text($path, $schedulePath);
    if ($scheduleXml === null) {
        $empty['error'] = t('Schedule_Input 시트를 읽지 못했습니다.', 'Could not read the Schedule_Input sheet.');
        return $empty;
    }
    $scheduleRows = ksers_glance_sheet_rows($scheduleXml, $strings);
    $headerRow = 0;
    $columns = [];
    foreach ($scheduleRows as $number => $cells) {
        $found = [];
        foreach ($cells as $column => $value) {
            $header = strtolower(trim((string)$value));
            if (!isset($found['start']) && strpos($header, 'start') !== false) {
                $found['start'] = $column;
            } elseif (!isset($found['end']) && strpos($header, 'end') !== false) {
                $found['end'] = $column;
            } elseif (!isset($found['abstract']) && strpos($header, 'abstract') !== false) {
                $found['abstract'] = $column;
            } elseif (!isset($found['color']) && strpos($header, 'color') !== false) {
                $found['color'] = $column;
            } elseif (!isset($found['category']) && strpos($header, 'category') !== false) {
                $found['category'] = $column;
            } elseif (!isset($found['session']) && strpos($header, 'session') !== false) {
                $found['session'] = $column;
            } elseif (!isset($found['room']) && strpos($header, 'room') !== false) {
                $found['room'] = $column;
            } elseif (!isset($found['day']) && strpos($header, 'day') !== false) {
                $found['day'] = $column;
            }
        }
        if (isset($found['day'], $found['room'], $found['start'], $found['end'])) {
            $headerRow = $number;
            $columns = $found;
            break;
        }
    }
    if ($headerRow === 0) {
        $empty['error'] = t(
            'Schedule_Input 시트에서 날짜, 룸, 시작, 종료 열을 찾지 못했습니다.',
            'Could not find the day, room, start, and end columns on Schedule_Input.'
        );
        return $empty;
    }
    $cell = function ($cells, $key) use ($columns) {
        if (!isset($columns[$key]) || !isset($cells[$columns[$key]])) {
            return '';
        }
        return trim((string)$cells[$columns[$key]]);
    };
    $records = [];
    $lastDay = '';
    foreach ($scheduleRows as $number => $cells) {
        if ($number <= $headerRow) {
            continue;
        }
        $day = $cell($cells, 'day');
        $room = $cell($cells, 'room');
        $startRaw = $cell($cells, 'start');
        $endRaw = $cell($cells, 'end');
        if ($day === '' && $room === '' && $startRaw === '' && $endRaw === '') {
            continue;
        }
        if ($day === '') {
            $day = $lastDay;
        }
        if ($day === '' || $room === '') {
            $empty['error'] = t(
                $number . '행에 날짜 또는 룸이 없습니다.',
                'Row ' . $number . ' is missing a day or a room.'
            );
            return $empty;
        }
        $start = ksers_glance_schedule_time($startRaw);
        $end = ksers_glance_schedule_time($endRaw);
        if ($start === '' || $end === '' || ksers_glance_schedule_minutes($end) <= ksers_glance_schedule_minutes($start)) {
            $empty['error'] = t(
                $number . '행의 시작·종료 시간을 확인해 주세요.',
                'Check the start and end time on row ' . $number . '.'
            );
            return $empty;
        }
        $lastDay = $day;
        $records[] = [
            'row' => $number,
            'day' => $day,
            'room' => $room,
            'category' => $cell($cells, 'category'),
            'session' => $cell($cells, 'session'),
            'topic' => $cell($cells, 'abstract'),
            'start' => $start,
            'end' => $end,
            'color' => $cell($cells, 'color'),
        ];
    }
    if (!$records) {
        $empty['error'] = t('넣을 시간표 행이 없습니다.', 'The sheet has no timetable rows.');
        return $empty;
    }

    $rooms = [];
    foreach ($sheets as $name => $target) {
        if (strcasecmp($name, 'Schedule_Input') === 0) {
            continue;
        }
        $xml = ksers_glance_zip_text($path, $target);
        if ($xml === null) {
            continue;
        }
        $rows = ksers_glance_sheet_rows($xml, $strings);
        $first = '';
        foreach ($rows as $cells) {
            foreach ($cells as $value) {
                $lines = ksers_glance_schedule_lines($value);
                if ($lines) {
                    $first = $lines[0];
                    break 2;
                }
            }
        }
        if ($first !== '') {
            $titles[strtolower($name)] = $first;
        }
        if (!$rooms && strcasecmp($name, $records[0]['day']) === 0) {
            $rooms = ksers_glance_schedule_rooms_from_sheet($rows, true);
        }
    }
    if (!$rooms) {
        foreach ($sheets as $name => $target) {
            if (strcasecmp($name, 'Schedule_Input') === 0) {
                continue;
            }
            $xml = ksers_glance_zip_text($path, $target);
            if ($xml === null) {
                continue;
            }
            $rooms = ksers_glance_schedule_rooms_from_sheet(ksers_glance_sheet_rows($xml, $strings));
            if ($rooms) {
                break;
            }
        }
    }
    $knownCodes = [];
    foreach ($rooms as $room) {
        $knownCodes[$room['code']] = true;
    }
    foreach ($records as $record) {
        if (preg_match('/^(all|전체)$/iu', trim($record['room']))) {
            continue;
        }
        foreach (ksers_glance_schedule_room_parts($record['room']) as $part) {
            if (ksers_glance_schedule_find_room($part, $rooms) !== '') {
                continue;
            }
            $room = ksers_glance_schedule_take_room($part, count($rooms) + 1, $knownCodes);
            $rooms[] = $room;
        }
    }
    if (!$rooms) {
        $empty['error'] = t('룸 이름을 읽지 못했습니다.', 'Could not read the room names.');
        return $empty;
    }
    foreach ($records as $record) {
        $parts = preg_split('/\s*[~～]\s*/u', trim($record['room']));
        if (count($parts) !== 2) {
            continue;
        }
        $startCode = ksers_glance_schedule_find_room($parts[0], $rooms);
        $endCode = ksers_glance_schedule_find_room($parts[1], $rooms);
        if (strlen($startCode) !== 1 || strlen($endCode) !== 1) {
            continue;
        }
        $startLetter = ord(strtoupper($startCode));
        $endLetter = ord(strtoupper($endCode));
        if ($startLetter < ord('A') || $endLetter > ord('Z') || $endLetter <= $startLetter) {
            continue;
        }
        for ($letter = $startLetter + 1; $letter < $endLetter; $letter++) {
            $code = chr($letter);
            if (isset($knownCodes[$code])) {
                continue;
            }
            $insertAt = count($rooms);
            foreach ($rooms as $index => $room) {
                if ($room['code'] === $endCode) {
                    $insertAt = $index;
                    break;
                }
            }
            $floor = '—';
            foreach ($rooms as $room) {
                if ($room['code'] === $startCode) {
                    $floor = $room['floor'];
                    break;
                }
            }
            array_splice($rooms, $insertAt, 0, [[
                'code' => $code,
                'floor' => $floor,
                'label' => 'Room ' . $code,
                'venue_name' => null,
                'sort_order' => 0,
            ]]);
            $knownCodes[$code] = true;
        }
    }
    foreach ($rooms as $index => $room) {
        $rooms[$index]['sort_order'] = $index + 1;
        if (!ksers_glance_valid_key($room['code'], 40)) {
            $empty['error'] = t('룸 구분값으로 쓸 수 없는 이름이 있습니다.', 'A room name cannot be used as an id.');
            return $empty;
        }
    }

    $days = [];
    $dayIds = [];
    $usedDay = [];
    foreach ($records as $record) {
        $key = strtolower($record['day']);
        if (isset($dayIds[$key])) {
            continue;
        }
        $label = isset($titles[$key]) ? $titles[$key] : $record['day'];
        $id = ksers_glance_schedule_unique_id($usedDay, $record['day'], 80);
        $dayIds[$key] = $id;
        $days[] = [
            'id' => $id,
            'label' => ksers_glance_schedule_cut($label, 160),
            'sort_order' => count($days) + 1,
        ];
    }

    $slotMap = [];
    $slots = [];
    $usedSlot = [];
    $slotSort = [];
    foreach ($records as $record) {
        $dayId = $dayIds[strtolower($record['day'])];
        $key = $dayId . '|' . $record['start'] . '|' . $record['end'];
        if (isset($slotMap[$key])) {
            continue;
        }
        $slotSort[$dayId] = isset($slotSort[$dayId]) ? $slotSort[$dayId] : 0;
        $id = ksers_glance_schedule_unique_id(
            $usedSlot,
            $dayId . '-' . str_replace(':', '', $record['start']) . '-' . str_replace(':', '', $record['end']),
            80
        );
        $slotMap[$key] = $id;
        $slots[] = [
            'id' => $id,
            'day_id' => $dayId,
            'time_label' => $record['start'] . '-' . $record['end'],
            'sort_order' => 0,
            'start' => ksers_glance_schedule_minutes($record['start']),
            'end' => ksers_glance_schedule_minutes($record['end']),
        ];
    }
    $dayOrder = [];
    foreach ($days as $index => $day) {
        $dayOrder[$day['id']] = $index;
    }
    usort($slots, function ($a, $b) use ($dayOrder) {
        $day = $dayOrder[$a['day_id']] <=> $dayOrder[$b['day_id']];
        if ($day !== 0) {
            return $day;
        }
        if ($a['start'] !== $b['start']) {
            return $a['start'] <=> $b['start'];
        }
        return $a['end'] <=> $b['end'];
    });
    $orderByDay = [];
    foreach ($slots as $index => $slot) {
        $orderByDay[$slot['day_id']] = isset($orderByDay[$slot['day_id']]) ? $orderByDay[$slot['day_id']] + 1 : 1;
        $slots[$index]['sort_order'] = $orderByDay[$slot['day_id']];
        unset($slots[$index]['start'], $slots[$index]['end']);
    }

    $sessions = [];
    $usedSession = [];
    $placed = [];
    $sessionSort = [];
    $warnings = [];
    $unknownColors = [];
    foreach ($records as $record) {
        $dayId = $dayIds[strtolower($record['day'])];
        $slotId = $slotMap[$dayId . '|' . $record['start'] . '|' . $record['end']];
        $roomText = trim($record['room']);
        if (preg_match('/^(all|전체)$/iu', $roomText)) {
            $roomCode = $rooms[0]['code'];
            $span = count($rooms);
        } else {
            $parts = ksers_glance_schedule_room_parts($roomText);
            if (!$parts) {
                $empty['error'] = t(
                    $record['row'] . '행의 룸을 표에서 찾지 못했습니다.',
                    'Row ' . $record['row'] . ' uses a room that is not on the table.'
                );
                return $empty;
            }
            $indexes = [];
            foreach ($parts as $part) {
                $code = ksers_glance_schedule_find_room($part, $rooms);
                if ($code === '') {
                    $empty['error'] = t(
                        $record['row'] . '행의 룸을 표에서 찾지 못했습니다.',
                        'Row ' . $record['row'] . ' uses a room that is not on the table.'
                    );
                    return $empty;
                }
                foreach ($rooms as $index => $room) {
                    if ($room['code'] === $code) {
                        $indexes[] = $index;
                        break;
                    }
                }
            }
            $startIndex = $indexes ? $indexes[0] : 0;
            $endIndex = $indexes ? $indexes[count($indexes) - 1] : 0;
            $previous = -1;
            foreach ($indexes as $index) {
                if ($index < $previous) {
                    $empty['error'] = t(
                        $record['row'] . '행의 룸 범위가 오른쪽이 아니라 왼쪽으로 갑니다.',
                        'Row ' . $record['row'] . ' covers rooms from right to left.'
                    );
                    return $empty;
                }
                $previous = $index;
            }
            $roomCode = $rooms[$startIndex]['code'];
            $span = $endIndex - $startIndex + 1;
        }
        $color = ksers_glance_schedule_color($record['color']);
        if ($color === null) {
            $unknownColors[$record['color']] = true;
            $color = 'none';
        }
        $sessionLines = ksers_glance_schedule_lines($record['session']);
        if ($record['category'] !== '') {
            $title = ksers_glance_schedule_cut(trim($record['category']), 255);
            $content = $sessionLines ? implode(' ', $sessionLines) : null;
        } else {
            $title = $sessionLines ? ksers_glance_schedule_cut($sessionLines[0], 255) : '';
            $content = count($sessionLines) > 1 ? implode(' ', array_slice($sessionLines, 1)) : null;
        }
        if ($title === '') {
            $empty['error'] = t(
                $record['row'] . '행에 세션명 또는 구분 태그가 없습니다.',
                'Row ' . $record['row'] . ' needs a session title or a category tag.'
            );
            return $empty;
        }
        $topic = ksers_glance_schedule_lines($record['topic']);
        $idBase = $dayId . '-' . ($record['category'] !== '' ? $record['category'] : $title) . '-' . str_replace(':', '', $record['start']) . '-' . $roomCode;
        $session = [
            'id' => ksers_glance_schedule_unique_id($usedSession, $idBase, 80),
            'slot_id' => $slotId,
            'room_code' => $roomCode,
            'column_span' => $span,
            'row_span' => 1,
            'title' => $title,
            'content' => $content === null ? null : ksers_glance_schedule_cut($content, 2000),
            'topic' => $topic ? ksers_glance_schedule_cut(implode(' ', $topic), 255) : null,
            'href' => null,
            'label' => null,
            'color' => $color,
            'sort_order' => 0,
        ];
        $daySlots = [];
        foreach ($slots as $slot) {
            if ($slot['day_id'] === $dayId) {
                $daySlots[] = $slot;
            }
        }
        $issue = ksers_glance_place_issue($rooms, $daySlots, isset($placed[$dayId]) ? $placed[$dayId] : [], $session);
        if ($issue !== '') {
            $empty['error'] = t($record['row'] . '행. ', 'Row ' . $record['row'] . '. ') . $issue;
            return $empty;
        }
        $sessionSort[$slotId] = isset($sessionSort[$slotId]) ? $sessionSort[$slotId] + 1 : 1;
        $session['sort_order'] = $sessionSort[$slotId];
        $placed[$dayId][] = $session;
        $sessions[] = $session;
    }
    if ($unknownColors) {
        $warnings[] = t(
            '파일의 색 이름을 시간표 색으로 맞추지 못해 빈 색으로 넣었습니다: ',
            'These color names were not recognized and were saved with no color: '
        ) . implode(', ', array_keys($unknownColors));
    }

    return [
        'error' => '',
        'warnings' => $warnings,
        'rooms' => $rooms,
        'days' => $days,
        'slots' => $slots,
        'sessions' => $sessions,
        'first_day' => $days[0]['id'],
    ];
}

function ksers_glance_replace_schedule($pdo, $year, $parsed) {
    $year = (int)$year;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM program_sessions WHERE year = ?')->execute([$year]);
        $pdo->prepare('DELETE FROM program_time_slots WHERE year = ?')->execute([$year]);
        $pdo->prepare('DELETE FROM program_days WHERE year = ?')->execute([$year]);
        $pdo->prepare('DELETE FROM program_rooms WHERE year = ?')->execute([$year]);
        $roomInsert = $pdo->prepare(
            'INSERT INTO program_rooms (year, code, floor, label, venue_name, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($parsed['rooms'] as $room) {
            $roomInsert->execute([$year, $room['code'], $room['floor'], $room['label'], $room['venue_name'], $room['sort_order']]);
        }
        $dayInsert = $pdo->prepare('INSERT INTO program_days (year, id, label, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($parsed['days'] as $day) {
            $dayInsert->execute([$year, $day['id'], $day['label'], $day['sort_order']]);
        }
        $slotInsert = $pdo->prepare(
            'INSERT INTO program_time_slots (year, id, day_id, time_label, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($parsed['slots'] as $slot) {
            $slotInsert->execute([$year, $slot['id'], $slot['day_id'], $slot['time_label'], $slot['sort_order']]);
        }
        $sessionInsert = $pdo->prepare(
            'INSERT INTO program_sessions
                (year, id, slot_id, room_code, column_span, row_span, title, content, topic, href, label, color, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($parsed['sessions'] as $session) {
            $sessionInsert->execute([
                $year, $session['id'], $session['slot_id'], $session['room_code'], $session['column_span'], $session['row_span'],
                $session['title'], $session['content'], $session['topic'], $session['href'], $session['label'], $session['color'], $session['sort_order'],
            ]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('glance schedule import failed: ' . $e->getMessage());
        return false;
    }
    return true;
}
