import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  StatusBar,
  SafeAreaView,
  Dimensions,
} from 'react-native';

const { width } = Dimensions.get('window');

// Poppy candy-colour palette for decoration
const CANDY_COLOURS = ['#FF2D55', '#FF9500', '#FFD700', '#00E676', '#2979FF', '#D500F9'];

export default function HomeScreen({ navigation }) {
  return (
    <View style={styles.container}>
      <StatusBar hidden />

      <SafeAreaView style={styles.safe}>
        {/* ── Top decorative strip ── */}
        <View style={styles.decorRow}>
          {CANDY_COLOURS.map((colour, i) => (
            <View key={i} style={[styles.decorDot, { backgroundColor: colour }]} />
          ))}
        </View>

        {/* ── Title ── */}
        <View style={styles.titleBlock}>
          <Text style={styles.titleTop}>🍬 CANDY</Text>
          <Text style={styles.titleBottom}>CRUSH 🍭</Text>
          <Text style={styles.tagline}>Match · Score · Level Up!</Text>
        </View>

        {/* ── Feature pills ── */}
        <View style={styles.pills}>
          {['8 × 8 Grid', 'Special Candies', 'Combos', '10 Levels'].map((label, i) => (
            <View key={i} style={[styles.pill, { borderColor: CANDY_COLOURS[i] }]}>
              <Text style={[styles.pillText, { color: CANDY_COLOURS[i] }]}>{label}</Text>
            </View>
          ))}
        </View>

        {/* ── Play button ── */}
        <TouchableOpacity
          style={styles.playBtn}
          onPress={() => navigation.navigate('Game')}
          activeOpacity={0.8}
        >
          <Text style={styles.playBtnText}>▶  PLAY NOW</Text>
        </TouchableOpacity>

        {/* ── How-to-play card ── */}
        <View style={styles.card}>
          <Text style={styles.cardTitle}>HOW TO PLAY</Text>
          <Text style={styles.cardLine}>• Tap two adjacent candies to swap them</Text>
          <Text style={styles.cardLine}>• Match 3+ in a row or column to score</Text>
          <Text style={styles.cardLine}>• Match 4 → Striped candy (row / col blast)</Text>
          <Text style={styles.cardLine}>• Match 5 → Colour Bomb (wipes one colour)</Text>
          <Text style={styles.cardLine}>• T / L shape → Wrapped candy (3 × 3 blast)</Text>
          <Text style={styles.cardLine}>• Reach the target score before moves run out!</Text>
        </View>

        {/* ── Bottom decorative strip ── */}
        <View style={styles.decorRow}>
          {[...CANDY_COLOURS].reverse().map((colour, i) => (
            <View key={i} style={[styles.decorDot, { backgroundColor: colour }]} />
          ))}
        </View>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#1A0A2E',
  },
  safe: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'space-evenly',
    paddingHorizontal: 20,
  },
  decorRow: {
    flexDirection: 'row',
    gap: 10,
  },
  decorDot: {
    width: 38,
    height: 38,
    borderRadius: 10,
    opacity: 0.85,
  },
  titleBlock: {
    alignItems: 'center',
  },
  titleTop: {
    fontSize: 52,
    fontWeight: '900',
    color: '#FF2D55',
    letterSpacing: 4,
    textShadowColor: '#FF6B8A',
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 18,
  },
  titleBottom: {
    fontSize: 52,
    fontWeight: '900',
    color: '#FFD700',
    letterSpacing: 4,
    textShadowColor: '#FFE066',
    textShadowOffset: { width: 0, height: 0 },
    textShadowRadius: 18,
  },
  tagline: {
    color: '#9988BB',
    fontSize: 13,
    letterSpacing: 1,
    marginTop: 6,
  },
  pills: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'center',
    gap: 8,
  },
  pill: {
    borderWidth: 1.5,
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 5,
    backgroundColor: 'rgba(255,255,255,0.05)',
  },
  pillText: {
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  playBtn: {
    backgroundColor: '#FF2D55',
    paddingVertical: 18,
    paddingHorizontal: 56,
    borderRadius: 50,
    shadowColor: '#FF2D55',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.75,
    shadowRadius: 14,
    elevation: 12,
  },
  playBtnText: {
    color: '#FFFFFF',
    fontSize: 22,
    fontWeight: '900',
    letterSpacing: 2,
  },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 18,
    padding: 20,
    width: '100%',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  cardTitle: {
    color: '#FFD700',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 2,
    textAlign: 'center',
    marginBottom: 10,
  },
  cardLine: {
    color: '#CCCCEE',
    fontSize: 13,
    lineHeight: 22,
  },
});
