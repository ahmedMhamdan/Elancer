// Screenshot-inspired completion composition using the local TailAdmin Modal and
// UserProfile card structure: rounded panel, profile summary and action footer.
// Content describes available Elancer features; profile setup does not publish it.
import { Link, usePage } from '@inertiajs/react';
import { Check, ShieldCheck, UserRound } from 'lucide-react';
import { useState } from 'react';
import Modal from '@/components/tailadmin/modal';
import Button from '@/components/tailadmin/button';
export default function OnboardingReady() {
    const [open, setOpen] = useState(true);
    const { auth } = usePage().props;
    const client = auth.user.workspace_role === 'client';
    return (
        <Modal
            open={open}
            onClose={() => setOpen(false)}
            title="Your profile is ready!"
            description="Welcome to Elancer. Your workspace is ready for the next step."
            className="max-w-4xl sm:p-10"
        >
            <div className="rounded-2xl bg-zinc-900 p-6 text-white sm:p-8">
                <div className="grid items-center gap-8 md:grid-cols-[1.4fr_1fr]">
                    <div>
                        <p className="text-sm font-medium text-emerald-300">
                            {client
                                ? 'Your client workspace'
                                : 'Your freelancer workspace'}
                        </p>
                        <h2 className="mt-3 text-2xl leading-tight font-semibold sm:text-3xl">
                            A great start.
                            <br />
                            Make it yours.
                        </h2>
                        <ul className="mt-6 space-y-4 text-sm text-zinc-200">
                            {[
                                'Your profile details are saved',
                                'Your skills and location are easy to update',
                                'Your account is ready to personalize',
                            ].map((text) => (
                                <li key={text} className="flex gap-3">
                                    <Check
                                        size={18}
                                        className="shrink-0 text-emerald-300"
                                        aria-hidden="true"
                                    />
                                    {client && text.includes('skills')
                                        ? 'Your introduction and location are easy to update'
                                        : text}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="rounded-2xl bg-white/10 p-6">
                        <div className="mb-4 flex size-16 items-center justify-center overflow-hidden rounded-full bg-white/10">
                            {auth.user.avatar ? (
                                <img
                                    src={auth.user.avatar}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <UserRound aria-hidden="true" size={28} />
                            )}
                        </div>
                        <p className="text-xl font-semibold">
                            {auth.user.name}
                        </p>
                        <p className="mt-2 text-sm text-zinc-300">
                            Your details are saved as a draft. Completing setup
                            does not publish your profile.
                        </p>
                    </div>
                </div>
                <div className="mt-8 flex flex-wrap items-center gap-4">
                    <Button onClick={() => setOpen(false)}>
                        Go to my workspace
                    </Button>
                    <Link
                        href="/my-profile"
                        className="min-h-11 px-2 py-3 text-sm underline underline-offset-4"
                    >
                        Review my profile
                    </Link>
                </div>
            </div>
            <Link
                href="/settings/security"
                className="text-muted-foreground hover:text-foreground mt-5 flex min-h-11 items-center gap-3 text-sm"
            >
                <ShieldCheck size={20} aria-hidden="true" />
                Keep your account secure with two-factor authentication.
            </Link>
        </Modal>
    );
}
