import { GRID_SIZE, CANDY_COLORS, BASE_POINTS } from '../constants/game';

// ─── Internal helpers ──────────────────────────────────────────────────────────

let _idCounter = 0;
function nextId() {
  return `c${++_idCounter}`;
}

function createCandy(type, special = null) {
  return { type, special, id: nextId() };
}

function deepCopy(board) {
  return board.map(row => row.map(candy => (candy ? { ...candy } : null)));
}

function randomType() {
  return Math.floor(Math.random() * CANDY_COLORS);
}

// Special-candy priority for conflict resolution (higher wins)
const SPECIAL_PRIORITY = { color_bomb: 3, wrapped: 2, striped_h: 1, striped_v: 1 };

// ─── Public API ────────────────────────────────────────────────────────────────

/**
 * Create an 8×8 board with no pre-existing matches.
 */
export function initBoard() {
  const board = Array.from({ length: GRID_SIZE }, () => Array(GRID_SIZE).fill(null));

  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      const forbidden = new Set();

      // Prevent horizontal 3-in-a-row
      if (c >= 2 && board[r][c - 1] && board[r][c - 2] &&
          board[r][c - 1].type === board[r][c - 2].type) {
        forbidden.add(board[r][c - 1].type);
      }
      // Prevent vertical 3-in-a-column
      if (r >= 2 && board[r - 1][c] && board[r - 2][c] &&
          board[r - 1][c].type === board[r - 2][c].type) {
        forbidden.add(board[r - 1][c].type);
      }

      let type;
      let attempts = 0;
      do {
        type = randomType();
        attempts++;
      } while (forbidden.has(type) && attempts < 20);

      board[r][c] = createCandy(type);
    }
  }

  return board;
}

/**
 * Find all match groups (3+ same-colour in a row or column).
 * Returns: Array<{ cells: {row,col}[], direction: 'h'|'v', count: number }>
 * Colour bombs are never part of a normal match group.
 */
export function findMatches(board) {
  const groups = [];

  // Horizontal sweeps
  for (let r = 0; r < GRID_SIZE; r++) {
    let c = 0;
    while (c < GRID_SIZE) {
      const candy = board[r][c];
      if (!candy || candy.special === 'color_bomb') { c++; continue; }

      let len = 1;
      while (
        c + len < GRID_SIZE &&
        board[r][c + len] &&
        board[r][c + len].special !== 'color_bomb' &&
        board[r][c + len].type === candy.type
      ) len++;

      if (len >= 3) {
        groups.push({
          cells: Array.from({ length: len }, (_, i) => ({ row: r, col: c + i })),
          direction: 'h',
          count: len,
        });
      }
      c += len;
    }
  }

  // Vertical sweeps
  for (let c = 0; c < GRID_SIZE; c++) {
    let r = 0;
    while (r < GRID_SIZE) {
      const candy = board[r][c];
      if (!candy || candy.special === 'color_bomb') { r++; continue; }

      let len = 1;
      while (
        r + len < GRID_SIZE &&
        board[r + len][c] &&
        board[r + len][c].special !== 'color_bomb' &&
        board[r + len][c].type === candy.type
      ) len++;

      if (len >= 3) {
        groups.push({
          cells: Array.from({ length: len }, (_, i) => ({ row: r + i, col: c })),
          direction: 'v',
          count: len,
        });
      }
      r += len;
    }
  }

  return groups;
}

/**
 * Process all match groups on the board.
 *
 * - Removes all matched cells (activating any special candies found within)
 * - Creates new special candies at the swap position (or group centre) when:
 *     count === 4          → striped (direction-dependent)
 *     count === 5          → colour bomb
 *     T / L intersection   → wrapped
 *
 * Returns { newBoard, score }
 */
export function processMatches(board, matchGroups, swapPos = null) {
  const newBoard = deepCopy(board);
  const toRemove = new Set();
  let totalScore = 0;

  // Count how many groups each cell belongs to (T/L detection)
  const cellGroupCount = {};
  matchGroups.forEach(({ cells }) => {
    cells.forEach(({ row, col }) => {
      const key = `${row},${col}`;
      cellGroupCount[key] = (cellGroupCount[key] || 0) + 1;
    });
  });

  // Per-position special-candy creation requests
  const specialCreations = {};

  function requestSpecial(row, col, special, type) {
    const key = `${row},${col}`;
    const current = specialCreations[key];
    if (!current || SPECIAL_PRIORITY[special] > SPECIAL_PRIORITY[current.special]) {
      specialCreations[key] = { type, special };
    }
  }

  // ── First pass: collect removals & activate specials in the match ──────────
  matchGroups.forEach(({ cells, direction, count }) => {
    cells.forEach(({ row, col }) => {
      const candy = newBoard[row][col];
      if (!candy) return;

      toRemove.add(`${row},${col}`);
      totalScore += BASE_POINTS;

      // Activate special candy that is part of this match
      if (candy.special === 'striped_h') {
        for (let c = 0; c < GRID_SIZE; c++) {
          toRemove.add(`${row},${c}`);
          totalScore += 20;
        }
      } else if (candy.special === 'striped_v') {
        for (let r = 0; r < GRID_SIZE; r++) {
          toRemove.add(`${r},${col}`);
          totalScore += 20;
        }
      } else if (candy.special === 'wrapped') {
        for (let dr = -1; dr <= 1; dr++) {
          for (let dc = -1; dc <= 1; dc++) {
            const nr = row + dr;
            const nc = col + dc;
            if (nr >= 0 && nr < GRID_SIZE && nc >= 0 && nc < GRID_SIZE) {
              toRemove.add(`${nr},${nc}`);
              totalScore += 20;
            }
          }
        }
      }
    });

    // ── Determine where to create a new special candy ──────────────────────
    // Prefer the swapped cell if it's inside this group, else use centre.
    let createPos = swapPos
      ? cells.find(c => c.row === swapPos.row && c.col === swapPos.col)
      : null;
    if (!createPos) createPos = cells[Math.floor(cells.length / 2)];

    const { row: cr, col: cc } = createPos;
    const candyType = newBoard[cr][cc]?.type;
    if (candyType == null) return;

    const createKey = `${cr},${cc}`;

    if (cellGroupCount[createKey] >= 2) {
      // T or L shape → wrapped
      requestSpecial(cr, cc, 'wrapped', candyType);
    } else if (count >= 5) {
      requestSpecial(cr, cc, 'color_bomb', candyType);
    } else if (count === 4) {
      requestSpecial(cr, cc, direction === 'h' ? 'striped_h' : 'striped_v', candyType);
    }
  });

  // ── Apply removals ─────────────────────────────────────────────────────────
  toRemove.forEach(key => {
    const [r, c] = key.split(',').map(Number);
    newBoard[r][c] = null;
  });

  // ── Place new special candies ──────────────────────────────────────────────
  Object.entries(specialCreations).forEach(([key, { type, special }]) => {
    const [r, c] = key.split(',').map(Number);
    newBoard[r][c] = createCandy(type, special);
  });

  return { newBoard, score: totalScore };
}

/**
 * Apply gravity: make every candy fall to the lowest available row in its column.
 */
export function applyGravity(board) {
  const newBoard = deepCopy(board);

  for (let c = 0; c < GRID_SIZE; c++) {
    let writeRow = GRID_SIZE - 1;
    for (let r = GRID_SIZE - 1; r >= 0; r--) {
      if (newBoard[r][c] !== null) {
        if (writeRow !== r) {
          newBoard[writeRow][c] = newBoard[r][c];
          newBoard[r][c] = null;
        }
        writeRow--;
      }
    }
  }

  return newBoard;
}

/**
 * Fill every null cell with a new random candy.
 */
export function fillBoard(board) {
  const newBoard = deepCopy(board);

  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (newBoard[r][c] === null) {
        newBoard[r][c] = createCandy(randomType());
      }
    }
  }

  return newBoard;
}

/**
 * Swap two cells and return the resulting board (no validation).
 */
export function performSwap(board, r1, c1, r2, c2) {
  const newBoard = deepCopy(board);
  const temp = newBoard[r1][c1];
  newBoard[r1][c1] = newBoard[r2][c2];
  newBoard[r2][c2] = temp;
  return newBoard;
}

/**
 * Return true if swapping the two cells would create at least one match,
 * or if either cell is a colour bomb (always valid).
 * Only adjacent cells (Manhattan distance === 1) are considered.
 */
export function isValidSwap(board, r1, c1, r2, c2) {
  if (Math.abs(r1 - r2) + Math.abs(c1 - c2) !== 1) return false;
  if (board[r1][c1]?.special === 'color_bomb' || board[r2][c2]?.special === 'color_bomb') {
    return true;
  }
  return findMatches(performSwap(board, r1, c1, r2, c2)).length > 0;
}

/**
 * Activate a colour bomb by swapping it with an adjacent candy.
 * Removes all candies sharing the target candy's colour.
 * Returns { newBoard, score }.
 */
export function activateColorBomb(board, bombR, bombC, targetR, targetC) {
  const targetType = board[targetR][targetC]?.type;
  if (targetType == null) return { newBoard: board, score: 0 };

  const newBoard = deepCopy(board);
  let totalScore = BASE_POINTS; // for the bomb itself

  newBoard[bombR][bombC] = null;

  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (newBoard[r][c]?.type === targetType) {
        newBoard[r][c] = null;
        totalScore += BASE_POINTS;
      }
    }
  }

  return { newBoard, score: totalScore };
}

/**
 * Return true if any swap on the board would create a match.
 */
export function hasValidMoves(board) {
  for (let r = 0; r < GRID_SIZE; r++) {
    for (let c = 0; c < GRID_SIZE; c++) {
      if (c + 1 < GRID_SIZE && isValidSwap(board, r, c, r, c + 1)) return true;
      if (r + 1 < GRID_SIZE && isValidSwap(board, r, c, r + 1, c)) return true;
    }
  }
  return false;
}
