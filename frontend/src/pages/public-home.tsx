import { useState, type FormEvent } from "react"

import { PageShell, ResponsiveGrid, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  StatCard,
  buttonClasses,
} from "@/components/ui"

type Availability = {
  label: string
  detail: string
}

type PlayArea = {
  departmentId: number
  name: string
  description: string
  entranceFeeTickets: number
  capacity: number
  activeAttendees: number
  availability: Availability
}

type UserSummary = {
  name: string
  role: string
} | null

export type PublicHomeProps = {
  user: UserSummary
  dashboardPath: string | null
  pendingApproval: boolean
  pdoError: string | null
  playAreas: PlayArea[]
}

function statusVariant(label: string) {
  if (label === "Open") {
    return "success" as const
  }
  if (label === "Out Of Order" || label === "Unavailable") {
    return "danger" as const
  }

  return "warning" as const
}

type AuthMode = "login" | "register" | "member"

type AuthResponse = {
  ok: boolean
  message: string
  user?: {
    name: string
    role: string
  }
  dashboardPath?: string
}

export function PublicHome({
  user,
  dashboardPath,
  pendingApproval,
  pdoError,
  playAreas,
}: PublicHomeProps) {
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const [authMode, setAuthMode] = useState<AuthMode>("login")
  const [authMessage, setAuthMessage] = useState<string | null>(null)
  const [authError, setAuthError] = useState<string | null>(null)
  const [loggedInUser, setLoggedInUser] = useState<UserSummary>(user)
  const [activeDashboardPath, setActiveDashboardPath] = useState<string | null>(dashboardPath)
  const activeCount = playAreas.reduce((total, area) => total + area.activeAttendees, 0)
  const capacityCount = playAreas.reduce((total, area) => total + area.capacity, 0)

  async function handleAuthSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const formData = new FormData(event.currentTarget)
    setAuthError(null)
    setAuthMessage(null)

    const response = await fetch("auth_modal.php", {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
    const result = (await response.json()) as AuthResponse

    if (!response.ok || !result.ok) {
      setAuthError(result.message || "Something went wrong.")
      return
    }

    setAuthMessage(result.message)
    if (result.user) {
      setLoggedInUser(result.user)
      setActiveDashboardPath(result.dashboardPath ?? "index.php")
      setIsLoginOpen(false)
      window.location.href = result.dashboardPath ?? "index.php"
    }
  }

  function openAuthModal(mode: AuthMode) {
    setAuthMode(mode)
    setAuthError(null)
    setAuthMessage(null)
    setIsLoginOpen(true)
  }

  return (
    <>
      <PageShell
        actions={
          loggedInUser ? (
            <>
              {activeDashboardPath && !pendingApproval ? (
                <a className={buttonClasses()} href={activeDashboardPath}>
                  Go to Dashboard
                </a>
              ) : null}
              <a className={buttonClasses({ variant: "neutral" })} href="logout.php">
                Logout
              </a>
            </>
          ) : (
            <>
              <Button onClick={() => openAuthModal("login")}>Staff Login</Button>
              <Button onClick={() => openAuthModal("member")} variant="neutral">
                Member Login
              </Button>
            </>
          )
        }
        description="Check live play-area availability before you head to the counter."
        eyebrow="Arcade Management"
        headerClassName="mx-auto w-full max-w-xl text-center lg:grid-cols-1"
        title="kewl Arcade"
        titleClassName="kewl-title mx-auto text-5xl font-black uppercase sm:text-7xl"
      >
      <section>
        <Card className="bg-main">
          <CardHeader>
            <CardTitle className="text-3xl text-main-foreground">
              Live Department Board
            </CardTitle>
            <CardDescription className="text-main-foreground">
              Entry prices are listed in tickets, and attendance updates from staff sessions.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <StatCard
              className="bg-secondary-background"
              detail="Configured play areas"
              label="Areas"
              value={playAreas.length}
            />
            <StatCard
              className="bg-secondary-background"
              detail="Current attendance"
              label="Players"
              value={activeCount}
            />
            <StatCard
              className="bg-secondary-background"
              detail="Total play-area capacity"
              label="Capacity"
              value={capacityCount}
            />
          </CardContent>
          <CardFooter className="text-main-foreground">
            {loggedInUser ? (
              <p className="m-0">
                Logged in as <strong>{loggedInUser.name}</strong> ({loggedInUser.role}).
              </p>
            ) : (
              <p className="m-0">Staff can log in to manage dashboards and ticket activity.</p>
            )}
            {pendingApproval ? (
              <Badge variant="accent">Account pending approval</Badge>
            ) : null}
          </CardFooter>
        </Card>
      </section>

      <Section
        title="Play Areas"
      >
        {pdoError ? (
          <Card className="bg-danger text-main-foreground">
            <CardTitle>Live availability unavailable</CardTitle>
            <CardDescription className="text-main-foreground">{pdoError}</CardDescription>
          </Card>
        ) : playAreas.length === 0 ? (
          <Card>
            <CardTitle>No play areas configured yet.</CardTitle>
            <CardDescription>
              Once departments are created, public availability will appear here.
            </CardDescription>
          </Card>
        ) : (
          <ResponsiveGrid>
            {playAreas.map((area) => (
              <Card className="relative pt-14" key={area.departmentId}>
                <Badge
                  className="absolute right-4 top-4"
                  variant={statusVariant(area.availability.label)}
                >
                  {area.availability.label}
                </Badge>
                <CardHeader>
                  <CardTitle>{area.name}</CardTitle>
                  <CardDescription>{area.description || "Arcade play area"}</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-base border-2 border-border bg-main p-3 text-main-foreground">
                      <p className="m-0 text-xs font-heading uppercase tracking-wide">Entry</p>
                      <p className="m-0 text-2xl font-heading">
                        {area.entranceFeeTickets.toLocaleString()} tickets
                      </p>
                    </div>
                    <div className="rounded-base border-2 border-border bg-accent p-3 text-accent-foreground">
                      <p className="m-0 text-xs font-heading uppercase tracking-wide">
                        Attendance
                      </p>
                      <p className="m-0 text-2xl font-heading">
                        {area.activeAttendees.toLocaleString()} /{" "}
                        {area.capacity.toLocaleString()}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </ResponsiveGrid>
        )}
      </Section>

      <footer className="flex flex-wrap items-center justify-between gap-3 pb-6 text-sm">
        <a className="font-heading underline" href="index.php?db_test=1">
          DB smoke test
        </a>
      </footer>
    </PageShell>
      {isLoginOpen ? (
        <div
          aria-labelledby="staff-login-title"
          aria-modal="true"
          className="fixed inset-0 z-50 grid place-items-center bg-overlay p-4"
          role="dialog"
        >
          <div className="w-full max-w-md rounded-base border-2 border-border bg-secondary-background p-6 shadow-smash">
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <h2 className="m-0 text-3xl" id="staff-login-title">
                  {authMode === "login"
                    ? "Staff Login"
                    : authMode === "register"
                      ? "Request Account"
                      : "Member Login"}
                </h2>
                <p className="m-0 mt-1 text-sm text-muted-foreground">
                  {authMode === "login"
                    ? "Sign in to continue to your dashboard."
                    : authMode === "register"
                      ? "Send an account request to the admin approval queue."
                      : "Member login is coming soon."}
                </p>
              </div>
              <Button
                aria-label="Close login popup"
                onClick={() => setIsLoginOpen(false)}
                size="sm"
                variant="neutral"
              >
                Close
              </Button>
            </div>
            {authError ? (
              <div className="mb-4 rounded-base border-2 border-border bg-danger p-3 text-sm font-heading text-main-foreground">
                {authError}
              </div>
            ) : null}
            {authMessage ? (
              <div className="mb-4 rounded-base border-2 border-border bg-success p-3 text-sm font-heading text-main-foreground">
                {authMessage}
                {loggedInUser ? (
                  <div className="mt-2">
                    Logged in as <strong>{loggedInUser.name}</strong>.
                  </div>
                ) : null}
              </div>
            ) : null}
            {authMode === "member" ? (
              <div className="grid gap-4">
                <p className="m-0 text-sm text-muted-foreground">
                  Member-facing login is not connected yet. Staff can still verify and manage member wallets from the employee dashboard.
                </p>
                <Button onClick={() => openAuthModal("login")}>Back To Staff Login</Button>
              </div>
            ) : (
              <form className="grid gap-4" method="post" onSubmit={handleAuthSubmit}>
                <input name="action" type="hidden" value={authMode} />
                {authMode === "register" ? (
                  <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-name">
                    Name
                    <input
                      className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      id="home-register-name"
                      name="name"
                      required
                      type="text"
                    />
                  </label>
                ) : null}
                <label className="grid gap-1 font-heading text-sm" htmlFor="home-auth-email">
                  Email
                  <input
                    className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    id="home-auth-email"
                    name="email"
                    required
                    type="email"
                  />
                </label>
                <label className="grid gap-1 font-heading text-sm" htmlFor="home-auth-password">
                  Password
                  <input
                    className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    id="home-auth-password"
                    name="password"
                    required
                    type="password"
                  />
                </label>
                {authMode === "register" ? (
                  <>
                    <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-confirm">
                      Confirm Password
                      <input
                        className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        id="home-register-confirm"
                        name="confirm_password"
                        required
                        type="password"
                      />
                    </label>
                    <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-role">
                      Role
                      <select
                        className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        id="home-register-role"
                        name="role"
                        required
                      >
                        <option value="employee">Employee</option>
                        <option value="owner">Owner</option>
                        <option value="hr">HR</option>
                      </select>
                    </label>
                  </>
                ) : null}
                <Button type="submit">
                  {authMode === "register" ? "Send Account Request" : "Sign In"}
                </Button>
                <Button
                  onClick={() => openAuthModal(authMode === "register" ? "login" : "register")}
                  type="button"
                  variant="neutral"
                >
                  {authMode === "register" ? "Back To Staff Login" : "Create Staff Account"}
                </Button>
              </form>
            )}
          </div>
        </div>
      ) : null}
    </>
  )
}
