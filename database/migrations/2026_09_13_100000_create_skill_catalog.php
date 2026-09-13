<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });
        Schema::create('profile_skill', function (Blueprint $table) {
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['profile_id', 'skill_id']);
        });
        // Starter vocabulary is part of the additive migration so existing installs
        // and fresh databases both get suggestions without running unrelated seeders.
        $names = ['Laravel', 'PHP', 'React', 'JavaScript', 'TypeScript', 'HTML', 'CSS', 'Tailwind CSS', 'Vue.js', 'Next.js', 'Node.js', 'Python', 'Django', 'Java', 'C#', 'C++', 'SQL', 'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Git', 'Docker', 'Linux', 'AWS', 'REST APIs', 'GraphQL', 'WordPress', 'Shopify', 'Flutter', 'React Native', 'Android', 'iOS', 'Swift', 'Kotlin', 'UI Design', 'UX Design', 'Figma', 'Graphic Design', 'Logo Design', 'Adobe Photoshop', 'Adobe Illustrator', 'Video Editing', 'Motion Graphics', 'Animation', '3D Modeling', 'Blender', 'Copywriting', 'Content Writing', 'Technical Writing', 'Translation', 'Arabic Translation', 'English Translation', 'SEO', 'Digital Marketing', 'Social Media Marketing', 'Email Marketing', 'Data Entry', 'Data Analysis', 'Microsoft Excel', 'Power BI', 'Machine Learning', 'Software Testing', 'Quality Assurance', 'Project Management', 'Virtual Assistance', 'Customer Support', 'Accounting', 'Bookkeeping', 'تصميم جرافيك', 'تطوير مواقع', 'كتابة محتوى', 'ترجمة', 'تسويق رقمي'];
        foreach ($names as $name) {
            DB::table('skills')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Preserve legacy JSON verbatim; backfill shared tags without deleting user data.
        DB::table('profiles')->orderBy('id')->chunkById(100, function ($profiles): void {
            foreach ($profiles as $profile) {
                $names = json_decode($profile->skills ?? '[]', true);
                foreach (is_array($names) ? $names : [] as $name) {
                    if (! is_string($name) || trim($name) === '') {
                        continue;
                    }
                    $name = trim($name);
                    DB::table('skills')->insertOrIgnore(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                    $id = DB::table('skills')->where('name', $name)->value('id');
                    DB::table('profile_skill')->insertOrIgnore(['profile_id' => $profile->id, 'skill_id' => $id]);
                }
            }
        });
    }

    public function down(): void
    {
        // Compatibility JSON retains the last saved selections on rollback.
        Schema::dropIfExists('profile_skill');
        Schema::dropIfExists('skills');
    }
};
