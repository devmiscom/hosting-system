import React, { useState, useRef, useCallback, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  Dimensions,
  StatusBar,
  Modal,
  SafeAreaView,
} from 'react-native';
import {
  initBoard,
  findMatches,
  processMatches,
  applyGravity,
  fillBoard,
  isValidSwap,
  performSwap,
  activateColorBomb,
  hasValidMoves,
} from '../utils/gameLogic';
import {
  GRID_SIZE,
  LEVEL_TARGETS,
  MOVES_PER_LEVEL,
  COMBO_BONUS_FACTOR,
} from '../constants/game';
import CandyPiece from '../components/CandyPiece';

// ─── Layout constants ──────────────────────────────────────────────────────────
const { width: SCREEN_WIDTH } = Dimensions.get('window');
const BOARD_PADDING = 8;
const CELL_GAP = 2;
const BOARD_INNER = SCREEN_WIDTH - BOARD_PADDING * 2;
const CELL_SIZE = (BOARD_INNER - CELL_GAP * (GRID_SIZE + 1)) / GRID_SIZE;

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

// ─── Component ─────────────────────────────────────────────────────────────────
export default function GameScreen({ navigation }) {
  // ── Source-of-truth refs (safe to read inside async callbacks) ──────────────
  const boardRef       = useRef(null);
  const scoreRef       = useRef(0);
  const movesRef       = useRef(MOVES_PER_LEVEL);
  const levelRef       = useRef(1);
  const processingRef  = useRef(false);
  const mountedRef     = useRef(true);

  // ── Render state ────────────────────────────────────────────────────────────
  const [board,            setBoard]            = useState(() => { const b = initBoard(); boardRef.current = b; return b; });
  const [score,            setScore]            = useState(0);
  const [moves,            setMoves]            = useState(MOVES_PER_LEVEL);
  const [level,            setLevel]            = useState(1);
  const [selectedCell,     setSelectedCell]     = useState(null);
  const [isProcessing,     setIsProcessing]     = useState(false);  // eslint-disable-line no-unused-vars
  const [showLevelComplete,setShowLevelComplete]= useState(false);
  const [showGameOver,     setShowGameOver]     = useState(false);
  const [comboDisplay,     setComboDisplay]     = useState(0);
  const [popupMsg,         setPopupMsg]         = useState(null);

  useEffect(() => {
    mountedRef.current = true;
    return () => { mountedRef.current = false; };
  }, []);

  // ── Helpers ─────────────────────────────────────────────────────────────────
  const syncBoard = useCallback(b => {
    boardRef.current = b;
    if (mountedRef.current) setBoard(b.map(row => [...row]));
  }, []);

  const flashPopup = useCallback(msg => {
    if (!mountedRef.current) return;
    setPopupMsg(msg);
    setTimeout(() => { if (mountedRef.current) setPopupMsg(null); }, 1300);
  }, []);

  /**
   * Cascade loop: repeatedly find matches → remove → gravity → refill
   * until the board is stable. Returns the final board and score.
   */
  const processCascade = useCallback(async (startBoard, startScore, swapPos) => {
    let currentBoard  = startBoard;
    let currentScore  = startScore;
    let cascadeDepth  = 0;

    while (mountedRef.current) {
      const matches = findMatches(currentBoard);
      if (matches.length === 0) break;

      const { newBoard, score: earned } = processMatches(currentBoard, matches, swapPos);
      const multiplier = cascadeDepth > 0 ? Math.pow(COMBO_BONUS_FACTOR, cascadeDepth) : 1;
      const points     = Math.round(earned * multiplier);

      currentScore += points;
      cascadeDepth++;

      scoreRef.current = currentScore;
      if (mountedRef.current) {
        setScore(currentScore);
        setComboDisplay(cascadeDepth);
        if (cascadeDepth > 1) flashPopup(`COMBO ×${cascadeDepth}!  +${points}`);
        syncBoard(newBoard);
      }
      await sleep(380);
      if (!mountedRef.current) break;

      const gravBoard = applyGravity(newBoard);
      syncBoard(gravBoard);
      await sleep(200);
      if (!mountedRef.current) break;

      currentBoard = fillBoard(gravBoard);
      syncBoard(currentBoard);
      await sleep(200);

      swapPos = null; // only relevant for the very first iteration
    }

    return { finalBoard: currentBoard, finalScore: currentScore };
  }, [syncBoard, flashPopup]);

  // ── Main tap handler ────────────────────────────────────────────────────────
  const handleCellPress = useCallback(async (row, col) => {
    if (processingRef.current || showGameOver || showLevelComplete) return;

    const currentBoard = boardRef.current;
    const candy = currentBoard[row][col];
    if (!candy) return;

    // ── Nothing selected yet → select this cell ────────────────────────────
    if (!selectedCell) {
      setSelectedCell({ row, col });
      return;
    }

    const { row: selRow, col: selCol } = selectedCell;

    // ── Tapped the same cell → deselect ───────────────────────────────────
    if (selRow === row && selCol === col) {
      setSelectedCell(null);
      return;
    }

    // ── Non-adjacent cell → re-select ─────────────────────────────────────
    if (Math.abs(selRow - row) + Math.abs(selCol - col) !== 1) {
      setSelectedCell({ row, col });
      return;
    }

    // ── Adjacent cell tapped → attempt swap ───────────────────────────────
    setSelectedCell(null);
    processingRef.current = true;
    setIsProcessing(true);

    const selCandy = currentBoard[selRow][selCol];
    const tapCandy = currentBoard[row][col];
    let nextBoard  = currentBoard;
    let nextScore  = scoreRef.current;

    // Colour-bomb activation (bomb × any candy)
    if (selCandy?.special === 'color_bomb' || tapCandy?.special === 'color_bomb') {
      const [bR, bC] = selCandy?.special === 'color_bomb' ? [selRow, selCol] : [row, col];
      const [tR, tC] = selCandy?.special === 'color_bomb' ? [row, col] : [selRow, selCol];
      const { newBoard, score: bombPoints } = activateColorBomb(currentBoard, bR, bC, tR, tC);
      nextScore += bombPoints;
      scoreRef.current = nextScore;
      setScore(nextScore);
      nextBoard = newBoard;
      syncBoard(nextBoard);
    } else if (isValidSwap(currentBoard, selRow, selCol, row, col)) {
      // Valid regular swap
      nextBoard = performSwap(currentBoard, selRow, selCol, row, col);
      syncBoard(nextBoard);
      await sleep(140);
    } else {
      // Invalid swap → briefly show it then revert
      const swapped = performSwap(currentBoard, selRow, selCol, row, col);
      syncBoard(swapped);
      await sleep(260);
      syncBoard(currentBoard);
      processingRef.current = false;
      setIsProcessing(false);
      return;
    }

    // Decrement moves
    movesRef.current -= 1;
    setMoves(movesRef.current);

    // Run cascade
    const swapPos = { row: selRow, col: selCol };
    const { finalBoard, finalScore } = await processCascade(nextBoard, nextScore, swapPos);

    if (!mountedRef.current) return;

    // Check win / lose conditions
    const target = LEVEL_TARGETS[levelRef.current - 1] ?? LEVEL_TARGETS[LEVEL_TARGETS.length - 1];
    if (finalScore >= target) {
      setShowLevelComplete(true);
    } else if (movesRef.current <= 0) {
      setShowGameOver(true);
    } else if (!hasValidMoves(finalBoard)) {
      // No moves left on board → reshuffle silently
      flashPopup('No moves — reshuffling!');
      await sleep(600);
      if (mountedRef.current) syncBoard(initBoard());
    }

    setComboDisplay(0);
    processingRef.current = false;
    setIsProcessing(false);
  }, [selectedCell, showGameOver, showLevelComplete, syncBoard, processCascade, flashPopup]);

  // ── Level-complete handler ──────────────────────────────────────────────────
  const handleNextLevel = useCallback(() => {
    levelRef.current += 1;
    movesRef.current  = MOVES_PER_LEVEL;
    setLevel(levelRef.current);
    setMoves(MOVES_PER_LEVEL);
    setShowLevelComplete(false);
    setComboDisplay(0);
    syncBoard(initBoard());
  }, [syncBoard]);

  // ── Restart handler ────────────────────────────────────────────────────────
  const handleRestart = useCallback(() => {
    levelRef.current  = 1;
    scoreRef.current  = 0;
    movesRef.current  = MOVES_PER_LEVEL;
    setLevel(1);
    setScore(0);
    setMoves(MOVES_PER_LEVEL);
    setShowGameOver(false);
    setShowLevelComplete(false);
    setComboDisplay(0);
    syncBoard(initBoard());
  }, [syncBoard]);

  // ── Derived display values ──────────────────────────────────────────────────
  const targetScore = LEVEL_TARGETS[level - 1] ?? LEVEL_TARGETS[LEVEL_TARGETS.length - 1];
  const progress    = Math.min(score / targetScore, 1);

  // ── Render ─────────────────────────────────────────────────────────────────
  return (
    <View style={styles.root}>
      <StatusBar hidden />

      {/* ── Header ── */}
      <SafeAreaView style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
          <Text style={styles.backArrow}>←</Text>
        </TouchableOpacity>

        {/* Score */}
        <View style={styles.headerCell}>
          <Text style={styles.headerLabel}>SCORE</Text>
          <Text style={styles.headerValue}>{score.toLocaleString()}</Text>
        </View>

        {/* Level + progress */}
        <View style={[styles.headerCell, styles.levelCell]}>
          <Text style={styles.levelLabel}>LEVEL {level}</Text>
          <View style={styles.progressTrack}>
            <View style={[styles.progressFill, { width: `${Math.round(progress * 100)}%` }]} />
          </View>
          <Text style={styles.progressHint}>{score.toLocaleString()} / {targetScore.toLocaleString()}</Text>
        </View>

        {/* Moves */}
        <View style={styles.headerCell}>
          <Text style={styles.headerLabel}>MOVES</Text>
          <Text style={[styles.headerValue, moves <= 5 && styles.movesWarning]}>{moves}</Text>
        </View>
      </SafeAreaView>

      {/* ── Floating combo / popup message ── */}
      {(popupMsg || comboDisplay > 1) && (
        <View style={styles.popupBanner} pointerEvents="none">
          <Text style={styles.popupText}>{popupMsg ?? `COMBO ×${comboDisplay}! 🔥`}</Text>
        </View>
      )}

      {/* ── Game board ── */}
      <View style={styles.boardWrapper}>
        <View style={styles.board}>
          {board.map((rowData, rowIdx) => (
            <View key={rowIdx} style={styles.boardRow}>
              {rowData.map((candy, colIdx) => (
                <View
                  key={colIdx}
                  style={[
                    styles.cell,
                    {
                      width:  CELL_SIZE + CELL_GAP,
                      height: CELL_SIZE + CELL_GAP,
                      backgroundColor:
                        (rowIdx + colIdx) % 2 === 0
                          ? 'rgba(255,255,255,0.04)'
                          : 'rgba(0,0,0,0.12)',
                    },
                  ]}
                >
                  {candy && (
                    <CandyPiece
                      candy={candy}
                      isSelected={
                        selectedCell?.row === rowIdx && selectedCell?.col === colIdx
                      }
                      cellSize={CELL_SIZE}
                      onPress={() => handleCellPress(rowIdx, colIdx)}
                    />
                  )}
                </View>
              ))}
            </View>
          ))}
        </View>
      </View>

      {/* ── Level Complete modal ── */}
      <Modal visible={showLevelComplete} transparent animationType="fade">
        <View style={styles.overlay}>
          <View style={styles.modalBox}>
            <Text style={styles.modalEmoji}>🎉</Text>
            <Text style={styles.modalTitle}>LEVEL COMPLETE!</Text>
            <Text style={styles.modalSub}>Level {level} cleared</Text>
            <Text style={styles.modalScore}>{score.toLocaleString()} pts</Text>
            <View style={styles.modalBtns}>
              <TouchableOpacity style={[styles.mBtn, styles.mBtnPrimary]} onPress={handleNextLevel}>
                <Text style={styles.mBtnPrimaryTxt}>NEXT LEVEL  →</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.mBtn, styles.mBtnSecondary]} onPress={() => navigation.goBack()}>
                <Text style={styles.mBtnSecondaryTxt}>MENU</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* ── Game Over modal ── */}
      <Modal visible={showGameOver} transparent animationType="fade">
        <View style={styles.overlay}>
          <View style={styles.modalBox}>
            <Text style={styles.modalEmoji}>💔</Text>
            <Text style={styles.modalTitle}>GAME OVER</Text>
            <Text style={styles.modalSub}>Out of moves!</Text>
            <Text style={styles.modalScore}>{score.toLocaleString()} pts</Text>
            <Text style={styles.modalTarget}>Target: {targetScore.toLocaleString()}</Text>
            <View style={styles.modalBtns}>
              <TouchableOpacity style={[styles.mBtn, styles.mBtnPrimary]} onPress={handleRestart}>
                <Text style={styles.mBtnPrimaryTxt}>TRY AGAIN</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.mBtn, styles.mBtnSecondary]} onPress={() => navigation.goBack()}>
                <Text style={styles.mBtnSecondaryTxt}>MENU</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

// ─── Styles ────────────────────────────────────────────────────────────────────
const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#1A0A2E',
  },

  // Header
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: 'rgba(0,0,0,0.35)',
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.08)',
  },
  backBtn: {
    padding: 6,
    marginRight: 4,
  },
  backArrow: {
    color: '#FFFFFF',
    fontSize: 24,
    fontWeight: '700',
  },
  headerCell: {
    flex: 1,
    alignItems: 'center',
  },
  headerLabel: {
    color: '#7766AA',
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  headerValue: {
    color: '#FFFFFF',
    fontSize: 20,
    fontWeight: '900',
  },
  movesWarning: {
    color: '#FF2D55',
  },
  levelCell: {
    flex: 2,
    paddingHorizontal: 6,
  },
  levelLabel: {
    color: '#FFD700',
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  progressTrack: {
    height: 5,
    width: '100%',
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderRadius: 3,
    marginVertical: 3,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: '#00E676',
    borderRadius: 3,
  },
  progressHint: {
    color: '#8877AA',
    fontSize: 9,
  },

  // Popup banner
  popupBanner: {
    position: 'absolute',
    top: 90,
    left: 0,
    right: 0,
    alignItems: 'center',
    zIndex: 200,
  },
  popupText: {
    color: '#FFD700',
    fontSize: 20,
    fontWeight: '900',
    textShadowColor: '#FF9500',
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 10,
    letterSpacing: 1,
  },

  // Board
  boardWrapper: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: BOARD_PADDING,
  },
  board: {
    backgroundColor: 'rgba(0,0,0,0.45)',
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1.5,
    borderColor: 'rgba(255,255,255,0.08)',
    padding: CELL_GAP,
  },
  boardRow: {
    flexDirection: 'row',
  },
  cell: {
    justifyContent: 'center',
    alignItems: 'center',
  },

  // Modals
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.78)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalBox: {
    backgroundColor: '#2A1A4E',
    borderRadius: 26,
    padding: 34,
    alignItems: 'center',
    width: '80%',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    shadowColor: '#9933FF',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.55,
    shadowRadius: 22,
    elevation: 22,
  },
  modalEmoji: {
    fontSize: 52,
    marginBottom: 12,
  },
  modalTitle: {
    color: '#FFFFFF',
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: 2,
    marginBottom: 6,
  },
  modalSub: {
    color: '#9988BB',
    fontSize: 14,
    marginBottom: 6,
  },
  modalScore: {
    color: '#FFD700',
    fontSize: 22,
    fontWeight: '800',
    marginBottom: 4,
  },
  modalTarget: {
    color: '#7766AA',
    fontSize: 13,
    marginBottom: 24,
  },
  modalBtns: {
    width: '100%',
    gap: 10,
  },
  mBtn: {
    paddingVertical: 14,
    borderRadius: 50,
    alignItems: 'center',
  },
  mBtnPrimary: {
    backgroundColor: '#FF2D55',
    shadowColor: '#FF2D55',
    shadowOffset: { width: 0, height: 5 },
    shadowOpacity: 0.65,
    shadowRadius: 10,
    elevation: 10,
  },
  mBtnPrimaryTxt: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 1.5,
  },
  mBtnSecondary: {
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
  },
  mBtnSecondaryTxt: {
    color: '#CCBBEE',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 1,
  },
});
