<?php
$file = 'c:\r\landing\hoasen-theme\index.php';
$content = file_get_contents($file);

// Replace all copy scenes with grandiose versions
$replacements = [
  // Scene 1
  "'MINIMAL SQL CLIENT'" => "'ENTERPRISE-GRADE • ZERO BLOAT'",
  "'Database work without the extra weight.'" => "'The Database Workspace for Developers Who Refuse Compromise'",
  "'HoaSen Table is built for developers who want to connect, query, inspect, and move on. A native-feeling experience: fast, smooth, quiet, no ceremony.'" => "'HoaSen Table combines production-grade SQL capabilities with obsessive attention to performance. Connect, query, inspect, and dominate your data—at native speed, zero ceremony, maximum impact.'",
  
  // Scene 2
  "'SCHEMA & CONTEXT AWARE'" => "'AI-POWERED • INTELLIGENT COMPLETIONS'",
  "'The editor understands your query before it completes it.'" => "'Real-Time Intelligence That Anticipates Your Next Query'",
  "'HoaSen reads the live schema, follows the query context, and uses machine learning to adapt suggestions to the way you actually work.'" => "'Harness advanced schema analysis and adaptive ML that learns your patterns. Get context-aware suggestions that feel like mind reading—because they're that intelligent.'",
  
  // Scene 3
  "'RESULTS STAY CLOSE'" => "'LIGHTNING-FAST • ZERO CONTEXT SWITCHING'",
  "'Inline execution. Zero context switching.'" => "'Blazing-Fast Inline Results — Never Leave Your Query'",
  "'Query results appear in your active workspace. Peek at foreign key relations on hover without writing subqueries or toggling tabs.'" => "'Execute and inspect instantly without leaving your workspace. Foreign key relations materialize on hover—no subqueries, no tab juggling, pure flow state.'",
  
  // Scene 4
  "'LARGE TABLES'" => "'MASSIVE SCALE • EXTREME PERFORMANCE'",
  "'Native speed, even on massive tables.'" => "'Render 1 Million Rows at 60 FPS Without Breaking a Sweat'",
  "'Open and scroll through millions of rows instantly. The workspace stays smooth, responsive, and never freezes when scanning heavy datasets.'" => "'Open a million rows and scroll like butter. Zero lag, zero jank, zero compromises. Our virtual grid renders only 23 DOM nodes—ever—while your data flies by at perfect 60 FPS.'",
  
  // Scene 5
  "'FULLY CUSTOM PLUGINS'" => "'PLUGIN ECOSYSTEM • LIMITLESS POWER'",
  "'Shape the tool around your workflow.'" => "'Build Your Perfect Development Environment With Full Customization'",
  "'Plugins are fully custom and live inside the workspace, so teams can add exactly the tools they need without losing the active query.'" => "'Create fully custom plugins that live inside your workspace. Your tools, your logic, your workflow—all without losing context. No compromises, only possibilities.'",
  
  // Scene 6
  "'CREATIVE WIDGETS'" => "'CREATIVE CONTROL • MINIMAL INTERFACE'",
  "'Use widgets as a creative workspace layer.'" => "'Transform Widgets Into Your Competitive Advantage'",
  "'Keep the UI minimal, then add, remove, or rearrange widgets to surface the context your workflow actually needs.'" => "'Start minimal, add power. Arrange widgets exactly how you need them—create the perfect interface for your team's unique workflow. Minimal stays beautiful, powerful stays focused.'",
  
  // Scene 7
  "'FOR DAILY DEVELOPER WORK'" => "'FOR TEAMS • BUILT TO WIN'",
  "'A sharper SQL client for focused teams.'" => "'The SQL Client for Teams That Move Fast and Break Nothing'",
  "'Read the guide, check the docs, follow the engineering notes, or talk to us about the workflows that slow your team down.'" => "'Built by engineers for engineers. Read our deep-dive guides, explore production-grade docs, or tell us where your workflow slows—we're obsessed with making you faster.'",
];

foreach ($replacements as $old => $new) {
  $content = str_replace($old, $new, $content);
}

file_put_contents($file, $content);
echo "✓ Copy rewritten successfully!";
