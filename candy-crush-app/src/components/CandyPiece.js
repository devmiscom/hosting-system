import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { CANDY_THEMES, SPECIAL_VISUALS } from '../constants/game';

/**
 * Renders a single candy tile.
 *
 * Props:
 *   candy      – { type: 0-5, special: null|string, id: string }
 *   isSelected – highlight the selected state
 *   cellSize   – outer bounding size in pixels
 *   onPress    – tap handler
 */
export default function CandyPiece({ candy, isSelected, cellSize, onPress }) {
  const theme = CANDY_THEMES[candy.type];
  const specialVisual = candy.special ? SPECIAL_VISUALS[candy.special] : null;

  // Inner candy square (slightly smaller than cell for gap illusion)
  const innerSize = cellSize - 6;
  const radius = innerSize * 0.22;

  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.8}
      style={[styles.wrapper, { width: cellSize, height: cellSize }]}
    >
      <View
        style={[
          styles.candy,
          {
            width: innerSize,
            height: innerSize,
            backgroundColor: theme.bg,
            borderRadius: radius,
            borderWidth: isSelected ? 3 : 1.5,
            borderColor: isSelected ? '#FFFFFF' : theme.shadow,
            shadowColor: isSelected ? '#FFFFFF' : theme.bg,
            shadowOpacity: isSelected ? 1 : 0.55,
            shadowRadius: isSelected ? 10 : 5,
            shadowOffset: { width: 0, height: 0 },
            elevation: isSelected ? 14 : 5,
            transform: [{ scale: isSelected ? 1.1 : 1 }],
          },
        ]}
      >
        {/* Glossy shine in the top-left corner */}
        <View
          style={[
            styles.shine,
            {
              width: innerSize * 0.38,
              height: innerSize * 0.22,
              borderRadius: innerSize * 0.08,
              backgroundColor: theme.highlight,
              top: innerSize * 0.1,
              left: innerSize * 0.1,
            },
          ]}
        />

        {/* Special-candy symbol overlay */}
        {specialVisual && (
          <View style={styles.specialOverlay}>
            <Text
              style={[
                styles.specialText,
                {
                  fontSize: innerSize * 0.44,
                  color: specialVisual.color,
                },
              ]}
            >
              {specialVisual.symbol}
            </Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  candy: {
    justifyContent: 'center',
    alignItems: 'center',
    overflow: 'hidden',
  },
  shine: {
    position: 'absolute',
    opacity: 0.45,
  },
  specialOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
  },
  specialText: {
    fontWeight: '900',
    textAlign: 'center',
    textShadowColor: 'rgba(0,0,0,0.5)',
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 3,
  },
});
