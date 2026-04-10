// Board dimensions
export const GRID_SIZE = 8;
export const CANDY_COLORS = 6;

/**
 * Visual theme for each candy colour type (0–5).
 * bg       – main fill colour
 * highlight – lighter centre shine
 * shadow   – darker border / drop shadow
 * name     – human-readable label
 */
export const CANDY_THEMES = [
  { bg: '#FF2D55', highlight: '#FF6B8A', shadow: '#C40030', name: 'Ruby' },
  { bg: '#FF9500', highlight: '#FFBB55', shadow: '#C46200', name: 'Tangerine' },
  { bg: '#FFD700', highlight: '#FFE85A', shadow: '#C4A000', name: 'Lemon' },
  { bg: '#00E676', highlight: '#69F0AE', shadow: '#00A349', name: 'Lime' },
  { bg: '#2979FF', highlight: '#82B1FF', shadow: '#0050C8', name: 'Sapphire' },
  { bg: '#D500F9', highlight: '#EA80FC', shadow: '#9B00CC', name: 'Amethyst' },
];

/**
 * Visual symbols shown on top of special candies.
 */
export const SPECIAL_VISUALS = {
  striped_h: { symbol: '↔', color: '#FFFFFF' },
  striped_v: { symbol: '↕', color: '#FFFFFF' },
  wrapped:   { symbol: '✦', color: '#FFD700' },
  color_bomb:{ symbol: '★', color: '#FFD700' },
};

/**
 * Score target for each level (index 0 = level 1).
 * After level 10 the last target keeps repeating.
 */
export const LEVEL_TARGETS = [
  1000, 2500, 4500, 7000, 10000,
  14000, 19000, 25000, 32000, 40000,
];

export const MOVES_PER_LEVEL = 25;
export const BASE_POINTS = 50;
export const COMBO_BONUS_FACTOR = 1.5;
